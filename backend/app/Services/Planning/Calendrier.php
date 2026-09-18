<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Models\AnneeAcademique;
use App\Models\Evenement;
use App\Models\Fermeture;
use App\Models\PeriodeSemestre;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Calendrier d'un établissement : quand ses cours ont lieu.
 *
 * Une séance n'est générée depuis l'emploi du temps que pendant la période du
 * semestre de son UE, hors fermetures. Sans période déclarée, rien n'est
 * généré : mieux vaut une alerte que des séances fantômes.
 */
class Calendrier
{
    public const SANS_PERIODE = 'sans_periode';
    public const HORS_PERIODE = 'hors_periode';
    public const FERMETURE = 'fermeture';

    /*
     * Mémoire d'un traitement : une génération interroge le calendrier pour
     * chaque créneau de chaque jour. Seuls motifDeRelache() et fermeturePour()
     * s'en servent. Les lectures des écrans (periodesDe, alertes) interrogent
     * toujours la base : un contrôleur garde son instance d'une requête à
     * l'autre (tests, Octane), et une période tout juste déclarée restait
     * invisible.
     */

    /** @var array<string, Collection<string, PeriodeSemestre>> */
    private array $periodes = [];

    /** @var Collection<int, Fermeture>|null */
    private ?Collection $fermetures = null;

    /**
     * Pourquoi aucune séance de ce semestre ne se tient ce jour-là ; null si
     * elle se tient.
     */
    public function motifDeRelache(?int $etablissementId, int $anneeId, ?int $semestre, Carbon $date): ?string
    {
        $periode = ($this->periodes[($etablissementId ?? 0) . "|{$anneeId}"] ??= $this->periodesDe($etablissementId, $anneeId))
            ->get(PeriodeSemestre::pariteDe($semestre ?: 1));

        if (!$periode) {
            return self::SANS_PERIODE;
        }

        $jour = $date->toDateString();

        if ($jour < $periode->date_debut->toDateString() || $jour > $periode->date_fin->toDateString()) {
            return self::HORS_PERIODE;
        }

        return $this->fermeturePour($etablissementId, $date) ? self::FERMETURE : null;
    }

    /** @return Collection<string, PeriodeSemestre> par parité */
    public function periodesDe(?int $etablissementId, int $anneeId): Collection
    {
        return PeriodeSemestre::query()
            ->where('annee_id', $anneeId)
            ->when(
                $etablissementId,
                fn ($q) => $q->where('etablissement_id', $etablissementId),
                fn ($q) => $q->whereNull('etablissement_id')
            )
            ->get()
            ->keyBy('parite');
    }

    /** Fermeture de l'université ou de l'établissement qui couvre ce jour. */
    public function fermeturePour(?int $etablissementId, Carbon $date): ?Fermeture
    {
        $this->fermetures ??= Fermeture::all();
        $jour = $date->toDateString();

        return $this->fermetures->first(fn (Fermeture $f) => ($f->etablissement_id === null || (int) $f->etablissement_id === (int) $etablissementId)
            && $f->date_debut->toDateString() <= $jour
            && $f->date_fin->toDateString() >= $jour);
    }

    /**
     * Séances à venir que la fermeture couvre, jamais ouvertes et sans
     * présence : celles d'aujourd'hui peuvent être en cours, on n'y touche pas.
     */
    public function seancesCouvertes(Fermeture $fermeture): Builder
    {
        $debut = max($fermeture->date_debut->toDateString(), today()->addDay()->toDateString());

        return Evenement::query()
            ->where('statut', 'planifie')
            ->whereDate('date', '>=', $debut)
            ->whereDate('date', '<=', $fermeture->date_fin->toDateString())
            ->whereDoesntHave('presences')
            ->when($fermeture->etablissement_id, fn ($q) => $q->whereIn(
                'filiere_id',
                fn ($f) => $f->select('id')->from('filieres')->where('etablissement_id', $fermeture->etablissement_id)
            ));
    }

    /**
     * Valide une fermeture : un type permis, des dates dans l'année. Sans fin,
     * un seul jour.
     *
     * @param  list<string>  $types
     * @return array{annee_id: int, type: string, libelle: string, date_debut: string, date_fin: string}
     */
    public function validerFermeture(array $donnees, array $types): array
    {
        $valeurs = Validator::make($donnees, [
            'annee_id'   => 'required|integer|exists:annees_academiques,id',
            'type'       => ['required', Rule::in($types)],
            'libelle'    => 'required|string|max:120',
            'date_debut' => 'required|date_format:Y-m-d',
            'date_fin'   => 'nullable|date_format:Y-m-d|after_or_equal:date_debut',
        ], [
            'type.in'                 => "Type de fermeture : vacances, examens ou autre. Les jours fériés sont déclarés par le super administrateur de l'université.",
            'date_fin.after_or_equal' => 'La fin vient au plus tôt le jour du début.',
        ])->validate();

        $valeurs['date_fin'] ??= $valeurs['date_debut'];
        $this->verifierDansAnnee(AnneeAcademique::findOrFail($valeurs['annee_id']), $valeurs);

        return $valeurs;
    }

    /** @param array{date_debut: string, date_fin: string} $valeurs */
    public function verifierDansAnnee(AnneeAcademique $annee, array $valeurs): void
    {
        if ($valeurs['date_debut'] < $annee->date_debut->toDateString() || $valeurs['date_fin'] > $annee->date_fin->toDateString()) {
            throw ValidationException::withMessages([
                'date_debut' => "Les dates doivent tenir dans {$annee->libelle}, du {$annee->date_debut->format('d/m/Y')} au {$annee->date_fin->format('d/m/Y')}.",
            ]);
        }
    }

    /**
     * Aperçu (les séances qu'elle retirerait) ou déclaration d'une fermeture.
     *
     * @return array{donnees: mixed, message: string, cree: bool}
     */
    public function apercuOuDeclaration(array $valeurs, bool $apercu): array
    {
        if ($apercu) {
            $n = $this->seancesCouvertes(new Fermeture($valeurs))->count();

            return [
                'donnees' => ['seances_a_retirer' => $n],
                'message' => $n > 0
                    ? "{$n} séance(s) à venir, jamais ouverte(s) et sans présence, seront retirées."
                    : "Aucune séance planifiée n'est concernée.",
                'cree'    => false,
            ];
        }

        [$fermeture, $retirees] = DB::transaction(function () use ($valeurs) {
            $fermeture = Fermeture::create($valeurs);

            return [$fermeture, $this->seancesCouvertes($fermeture)->delete()];
        });

        $dates = $fermeture->date_debut->equalTo($fermeture->date_fin)
            ? "le {$fermeture->date_debut->format('d/m/Y')}"
            : "du {$fermeture->date_debut->format('d/m/Y')} au {$fermeture->date_fin->format('d/m/Y')}";

        return [
            'donnees' => $fermeture->setAttribute('seances_retirees', $retirees),
            'message' => "« {$fermeture->libelle} » déclarée {$dates}. "
                . ($retirees > 0 ? "{$retirees} séance(s) à venir retirée(s)." : "Aucune séance planifiée n'était concernée."),
            'cree'    => true,
        ];
    }

    /**
     * Parités sans période déclarée pour l'année : leurs séances ne sont pas
     * générées.
     *
     * @return list<array{parite: string, message: string}>
     */
    public function alertes(?int $etablissementId, AnneeAcademique $annee): array
    {
        $periodes = $this->periodesDe($etablissementId, $annee->id);

        return collect(PeriodeSemestre::PARITES)
            ->reject(fn (string $parite) => $periodes->has($parite))
            ->map(fn (string $parite) => [
                'parite'  => $parite,
                'message' => PeriodeSemestre::LIBELLES[$parite] . " : aucune période déclarée pour {$annee->libelle}. Leurs séances ne sont pas générées depuis l'emploi du temps.",
            ])
            ->values()
            ->all();
    }
}
