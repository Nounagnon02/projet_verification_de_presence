<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Evenement;
use App\Services\Planning\Conflits;
use App\Services\Schedule\ValidateurCreneau;
use App\Services\ScheduleSlotResolver;
use App\Traits\ScopedByEtablissement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * L'emploi du temps hebdomadaire : ses créneaux se consultent, se créent, se
 * modifient et se suppriment ici. Il n'était alimenté que par import, sans
 * retour possible sur une erreur.
 *
 * Chaque écriture passe par ValidateurCreneau, comme les imports : mêmes
 * règles, mêmes conflits.
 */
class EmploiDuTempsController extends Controller
{
    use ScopedByEtablissement;

    public function __construct(
        private readonly Conflits $conflits,
        private readonly ScheduleSlotResolver $resolver,
    ) {}

    /**
     * GET /api/admin/emploi-du-temps?annee_id=&filiere_id=&semestre=
     *
     * Les créneaux que suit la filière, cours communs compris, avec leurs
     * conflits : ceux-ci se cherchent dans tout l'emploi du temps de l'année.
     */
    public function index(Request $request): JsonResponse
    {
        $annee = $this->annee($request);

        if (!$annee) {
            return $this->successResponse([]);
        }

        $tous = $this->creneaux($request, $annee->id)->get();
        $conflits = $this->conflitsParCreneau($tous);

        $filiereId = $request->integer('filiere_id') ?: null;
        $semestre = $request->integer('semestre') ?: null;

        $liste = $tous
            ->filter(fn (EmploiDuTemps $c) => (!$filiereId || $c->ec?->ue?->filieres->contains('id', $filiereId))
                && (!$semestre || (int) $c->ec?->ue?->semestre === $semestre))
            ->map(fn (EmploiDuTemps $c) => $this->presenter($c, $conflits[$c->id] ?? []))
            ->values();

        return $this->successResponse($liste);
    }

    /** POST /api/admin/emploi-du-temps */
    public function store(Request $request): JsonResponse
    {
        $valeurs = $this->valider($request);
        $ec = Ec::with('ue')->findOrFail($valeurs['ec_id']);
        $this->authorizeEtablissement($ec->ue, $request, 'filiere');
        $this->refuserSiAnneeClose((int) $ec->ue->annee_id, $request);

        [$creneau, $motifs] = $this->verifier($ec, $valeurs);

        if ($creneau === null) {
            return $this->errorResponse(implode(' ', $motifs), 422);
        }

        $cree = EmploiDuTemps::create($this->attributs($ec, $creneau));

        return $this->createdResponse(
            $this->presenter($this->recharger($cree)),
            "Créneau ajouté : {$ec->code}, " . mb_strtolower(EmploiDuTemps::JOURS[$cree->jour_semaine]) . " de {$creneau['heure_debut']} à {$creneau['heure_fin']}."
        );
    }

    /**
     * Les séances déjà générées depuis l'ancienne version du créneau sont
     * retirées (à venir, jamais ouvertes, sans présence) : laissées en place,
     * elles se tiendraient à l'ancienne heure ou dans l'ancienne salle. La
     * génération de la nuit recrée celles de la nouvelle version.
     *
     * PUT /api/admin/emploi-du-temps/{creneau}
     */
    public function update(Request $request, EmploiDuTemps $creneau): JsonResponse
    {
        $this->authorizeEtablissement($creneau, $request, 'filiere');
        $this->refuserSiAnneeClose((int) $creneau->annee_id, $request);

        $valeurs = $this->valider($request);
        $ec = Ec::with('ue')->findOrFail($valeurs['ec_id']);
        $this->authorizeEtablissement($ec->ue, $request, 'filiere');
        $this->refuserSiAnneeClose((int) $ec->ue->annee_id, $request);

        [$verifie, $motifs] = $this->verifier($ec, $valeurs, $creneau->id);

        if ($verifie === null) {
            return $this->errorResponse(implode(' ', $motifs), 422);
        }

        $retirees = $this->resolver->seancesAVenir($creneau)->delete();
        $creneau->update($this->attributs($ec, $verifie));

        return $this->successResponse(
            $this->presenter($this->recharger($creneau)),
            'Créneau modifié.' . ($retirees > 0
                ? " {$retirees} séance(s) à venir de l'ancienne version retirée(s) : la génération de cette nuit recrée celles de la nouvelle."
                : '')
        );
    }

    /** DELETE /api/admin/emploi-du-temps/{creneau} */
    public function destroy(Request $request, EmploiDuTemps $creneau): JsonResponse
    {
        $this->authorizeEtablissement($creneau, $request, 'filiere');
        $this->refuserSiAnneeClose((int) $creneau->annee_id, $request);

        $retirees = $this->resolver->seancesAVenir($creneau)->delete();
        $creneau->delete();

        return $this->successResponse(
            null,
            'Créneau supprimé.' . ($retirees > 0 ? " {$retirees} séance(s) à venir retirée(s)." : '')
        );
    }

    /**
     * Conflits déjà en base, créneaux et séances de l'année : ils datent
     * d'avant la règle commune, et rien ne les corrige d'office.
     *
     * GET /api/admin/emploi-du-temps/conflits?annee_id=
     */
    public function conflits(Request $request): JsonResponse
    {
        $annee = $this->annee($request);

        if (!$annee) {
            return $this->successResponse(['creneaux' => [], 'seances' => []]);
        }

        $creneaux = [];

        foreach ($this->creneaux($request, $annee->id)->get()->groupBy('jour_semaine') as $jour => $duJour) {
            array_push($creneaux, ...$this->conflits->paires(
                $duJour,
                EmploiDuTemps::JOURS[$jour] ?? '',
                fn (EmploiDuTemps $a, EmploiDuTemps $b) => Conflits::validitesSeRecouvrent(
                    $a->valide_du?->toDateString(), $a->valide_au?->toDateString(), $b->valide_du?->toDateString(), $b->valide_au?->toDateString()
                )
            ));
        }

        $requete = Evenement::with([...Conflits::CHARGEMENTS, 'salleRef:id,nom'])
            ->where('annee_id', $annee->id)
            ->where('statut', '!=', 'annule')
            ->orderBy('date')
            ->orderBy('heure_debut');
        $this->scopeViaRelation($requete, $request, 'filiere');

        $seances = [];
        $aujourdhui = today()->toDateString();

        foreach ($requete->get()->groupBy(fn (Evenement $e) => $e->date->toDateString()) as $jour => $duJour) {
            foreach ($this->conflits->paires($duJour, 'le ' . Carbon::parse($jour)->format('d/m/Y')) as $paire) {
                $seances[] = $paire + ['date' => $jour, 'a_venir' => $jour >= $aujourdhui];
            }
        }

        return $this->successResponse(
            ['annee' => $annee->libelle, 'creneaux' => $creneaux, 'seances' => $seances],
            count($creneaux) . " conflit(s) dans l'emploi du temps, " . count($seances) . " entre séances de {$annee->libelle}."
        );
    }

    private function annee(Request $request): ?AnneeAcademique
    {
        return $request->filled('annee_id')
            ? AnneeAcademique::findOrFail($request->integer('annee_id'))
            : AnneeAcademique::activePour($this->getEtablissementId($request));
    }

    /** Créneaux de l'année dans l'établissement, avec ce qu'il faut pour les décrire. */
    private function creneaux(Request $request, int $anneeId): Builder
    {
        $requete = EmploiDuTemps::with([...Conflits::CHARGEMENTS, 'salle:id,nom'])
            ->where('annee_id', $anneeId)
            ->orderBy('jour_semaine')
            ->orderBy('heure_debut');
        $this->scopeViaRelation($requete, $request, 'filiere');

        return $requete;
    }

    /**
     * @param  Collection<int, EmploiDuTemps>  $creneaux
     * @return array<int, list<string>>
     */
    private function conflitsParCreneau(Collection $creneaux): array
    {
        $occupations = $creneaux->mapWithKeys(fn (EmploiDuTemps $c) => [$c->id => $this->conflits->occupation($c)]);
        $resultat = [];

        foreach ($creneaux as $c) {
            $autres = $creneaux
                ->filter(fn (EmploiDuTemps $o) => $o->id !== $c->id
                    && $o->jour_semaine === $c->jour_semaine
                    && Conflits::validitesSeRecouvrent($c->valide_du?->toDateString(), $c->valide_au?->toDateString(), $o->valide_du?->toDateString(), $o->valide_au?->toDateString()))
                ->map(fn (EmploiDuTemps $o) => $occupations[$o->id]);

            $resultat[$c->id] = $this->conflits->entre($occupations[$c->id], $autres, EmploiDuTemps::JOURS[$c->jour_semaine] ?? '');
        }

        return $resultat;
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        return $request->validate([
            'ec_id'        => 'required|integer|exists:ecs,id',
            'jour_semaine' => 'required|integer|between:1,7',
            'heure_debut'  => 'required|date_format:H:i',
            'heure_fin'    => 'required|date_format:H:i|after:heure_debut',
            'type_cours'   => 'required|string|in:cm,td,tp,evaluation',
            'salle_id'     => 'nullable|integer|exists:salles,id',
            'groupe_id'    => 'nullable|integer',
            'enseignant'   => 'nullable|string|max:255',
            'valide_du'    => 'nullable|date_format:Y-m-d',
            'valide_au'    => 'nullable|date_format:Y-m-d|after_or_equal:valide_du',
        ], [
            'heure_fin.after'          => "L'heure de fin vient après l'heure de début.",
            'valide_au.after_or_equal' => 'La fin de validité vient au plus tôt le jour de son début.',
        ]);
    }

    /**
     * Le créneau passé au validateur commun, préparé pour la filière qui porte
     * l'UE ; $sauf, le créneau modifié.
     *
     * @return array{0: ?array<string, mixed>, 1: list<string>}  le créneau validé, ou les motifs du refus
     */
    private function verifier(Ec $ec, array $valeurs, ?int $sauf = null): array
    {
        $validateur = new ValidateurCreneau();
        $preparation = $validateur->preparer((int) $ec->ue->filiere_id, (int) $ec->ue->annee_id, $sauf);

        if (!$preparation['ok']) {
            return [null, [$preparation['motif']]];
        }

        $resultat = $validateur->valider([
            'ec_id'        => $ec->id,
            'ec_code'      => $ec->code,
            'jour_semaine' => (int) $valeurs['jour_semaine'],
            'heure_debut'  => $valeurs['heure_debut'],
            'heure_fin'    => $valeurs['heure_fin'],
            'salle_id'     => $valeurs['salle_id'] ?? null,
            'sans_salle'   => empty($valeurs['salle_id']),
            'type_seance'  => $valeurs['type_cours'],
            'groupe_id'    => $valeurs['groupe_id'] ?? null,
            'enseignants'  => Conflits::enseignants($valeurs['enseignant'] ?? null),
            'valide_du'    => $valeurs['valide_du'] ?? null,
            'valide_au'    => $valeurs['valide_au'] ?? null,
        ]);

        return $resultat['statut'] === ValidateurCreneau::VALIDE
            ? [$resultat['creneau'], []]
            : [null, $resultat['motifs']];
    }

    /** @return array<string, mixed> */
    private function attributs(Ec $ec, array $c): array
    {
        return [
            'ec_id'         => $ec->id,
            'filiere_id'    => $ec->ue->filiere_id,
            'annee_id'      => $ec->ue->annee_id,
            'jour_semaine'  => $c['jour_semaine'],
            'heure_debut'   => $c['heure_debut'],
            'heure_fin'     => $c['heure_fin'],
            'salle_id'      => $c['salle_id'],
            'salle_libelle' => $c['salle_libelle'],
            'type_cours'    => $c['type_cours'],
            'groupe_id'     => $c['groupe_id'],
            'enseignant'    => $c['enseignant'],
            'valide_du'     => $c['valide_du'],
            'valide_au'     => $c['valide_au'],
        ];
    }

    private function recharger(EmploiDuTemps $c): EmploiDuTemps
    {
        return $c->fresh([...Conflits::CHARGEMENTS, 'salle:id,nom']);
    }

    /** @return array<string, mixed> */
    private function presenter(EmploiDuTemps $c, array $conflits = []): array
    {
        return [
            'id'           => $c->id,
            'ec_id'        => $c->ec_id,
            'ec'           => ['id' => $c->ec?->id, 'code' => $c->ec?->code, 'intitule' => $c->ec?->intitule],
            'ue'           => ['code' => $c->ec?->ue?->code, 'semestre' => $c->ec?->ue?->semestre],
            'filieres'     => $c->ec?->ue?->filieres->pluck('code')->values()->all() ?? [],
            'filiere_id'   => $c->filiere_id,
            'annee_id'     => $c->annee_id,
            'jour_semaine' => $c->jour_semaine,
            'heure_debut'  => substr((string) $c->heure_debut, 0, 5),
            'heure_fin'    => substr((string) $c->heure_fin, 0, 5),
            'type_cours'   => $c->type_cours,
            'salle_id'     => $c->salle_id,
            'salle'        => $c->salle?->nom ?? $c->salle_libelle,
            'groupe_id'    => $c->groupe_id,
            'groupe'       => $c->groupe?->libelle,
            'enseignant'   => $c->enseignant,
            'valide_du'    => $c->valide_du?->toDateString(),
            'valide_au'    => $c->valide_au?->toDateString(),
            'conflits'     => $conflits,
        ];
    }
}
