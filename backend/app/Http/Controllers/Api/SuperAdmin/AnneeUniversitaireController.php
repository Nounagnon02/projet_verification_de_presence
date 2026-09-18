<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\Etablissement;
use App\Services\BasculeAnnee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Années académiques de l'université, gérées par le super administrateur.
 *
 * Il les crée, fixe leurs dates de référence, les supprime, et désigne l'année
 * en cours de l'université. Chaque établissement choisit ensuite celle sur
 * laquelle il travaille (Admin\AnneeAcademiqueController::activate).
 */
class AnneeUniversitaireController extends Controller
{
    public function __construct(private readonly BasculeAnnee $bascule) {}

    /** GET /api/super-admin/annees-academiques */
    public function index(): JsonResponse
    {
        $etablissements = Etablissement::orderBy('code')->get(['id', 'code', 'nom', 'annee_active_id']);
        $enCours = AnneeAcademique::where('active', true)->value('id');

        $annees = AnneeAcademique::withCount(['evenements', 'ues', 'emploisDuTemps'])
            ->avecEffectifs()
            ->orderBy('date_debut', 'desc')
            ->get()
            ->each(function (AnneeAcademique $annee) use ($etablissements, $enCours) {
                $annee->setAttribute('statut', $annee->statut());
                // Établissements qui travaillent sur cette année : par leur choix,
                // ou parce qu'ils suivent l'année en cours de l'université.
                $annee->setAttribute('etablissements', $etablissements
                    ->filter(fn ($e) => (int) ($e->annee_active_id ?? $enCours) === (int) $annee->id)
                    ->map(fn ($e) => ['code' => $e->code, 'nom' => $e->nom, 'suit_universite' => $e->annee_active_id === null])
                    ->values()->all());

                // Ce que changer l'année en cours retirerait chez ceux qui la suivent.
                if ($annee->active) {
                    $annee->setAttribute('seances_a_venir_count', $this->bascule->seancesAVenir($annee->id, $this->bascule->suiveurs())->count());
                }
            });

        return $this->successResponse($annees);
    }

    /** POST /api/super-admin/annees-academiques */
    public function store(Request $request): JsonResponse
    {
        $valeurs = $this->valider($request);

        $annee = AnneeAcademique::create($valeurs + ['active' => false]);

        // La toute première année devient l'année en cours.
        if (!AnneeAcademique::where('active', true)->exists()) {
            $annee->update(['active' => true]);
        }

        return $this->createdResponse($annee->fresh(), "Année {$annee->libelle} créée.");
    }

    /** PUT /api/super-admin/annees-academiques/{annee} */
    public function update(Request $request, AnneeAcademique $annee): JsonResponse
    {
        $annee->update($this->valider($request, $annee));

        return $this->successResponse($annee->fresh(), "Année {$annee->libelle} mise à jour.");
    }

    /**
     * Les tables liées à une année sont en suppression en cascade : UE (et
     * leurs EC), emploi du temps, inscriptions aux EC, étudiants, séances. Le
     * contrôle ne regardait que les étudiants et les séances : une année
     * préparée, avec sa maquette mais sans étudiant, disparaissait entière.
     *
     * DELETE /api/super-admin/annees-academiques/{annee}
     */
    public function destroy(AnneeAcademique $annee): JsonResponse
    {
        if ($annee->active) {
            return $this->errorResponse("{$annee->libelle} est l'année en cours de l'université : désignez-en une autre avant de la supprimer.", 409);
        }

        $surCetteAnnee = Etablissement::where('annee_active_id', $annee->id)->orderBy('code')->pluck('code');

        if ($surCetteAnnee->isNotEmpty()) {
            return $this->errorResponse("{$annee->libelle} est l'année active de : {$surCetteAnnee->implode(', ')}.", 409);
        }

        $contenu = array_filter([
            'étudiant(s)'                     => $annee->etudiants()->count(),
            'séance(s)'                       => $annee->evenements()->count(),
            'UE'                              => $annee->ues()->count(),
            "créneau(x) d'emploi du temps"    => $annee->emploisDuTemps()->count(),
            'inscription(s) aux EC'           => DB::table('etudiant_ec')->where('annee_id', $annee->id)->count(),
        ]);

        if ($contenu !== []) {
            $liste = implode(', ', array_map(fn ($n, $libelle) => "{$n} {$libelle}", $contenu, array_keys($contenu)));

            return $this->errorResponse("Impossible de supprimer {$annee->libelle} : elle contient {$liste}, que sa suppression effacerait.", 409);
        }

        $annee->delete();

        return $this->successResponse(null, "Année {$annee->libelle} supprimée.");
    }

    /**
     * Désigne l'année en cours de l'université. Les établissements qui ont
     * choisi leur année la gardent ; les autres suivent celle-ci.
     *
     * PATCH /api/super-admin/annees-academiques/{annee}/en-cours
     */
    public function enCours(AnneeAcademique $annee): JsonResponse
    {
        $retirees = $this->bascule->changerAnneeEnCours($annee);

        $message = "{$annee->libelle} est l'année en cours de l'université. Les établissements qui ont choisi leur année la gardent.";
        if ($retirees > 0) {
            $message .= " {$retirees} séance(s) déjà planifiée(s) de l'année précédente ont été retirées chez les autres.";
        }

        return $this->successResponse($annee->fresh()->setAttribute('seances_retirees', $retirees), $message);
    }

    /**
     * Libellé AAAA-AAAA, années consécutives, dates cohérentes avec le libellé,
     * aucun chevauchement : on pouvait créer « 2026-2027 » du 1er janvier au
     * 3 mars, ou deux années qui se recouvrent.
     *
     * @return array{libelle: string, date_debut: string, date_fin: string}
     */
    private function valider(Request $request, ?AnneeAcademique $sauf = null): array
    {
        $valeurs = $request->validate([
            'libelle'    => ['required', 'string', 'regex:/^\d{4}-\d{4}$/', Rule::unique('annees_academiques', 'libelle')->ignore($sauf?->id)],
            'date_debut' => ['required', 'date_format:Y-m-d'],
            'date_fin'   => ['required', 'date_format:Y-m-d', 'after:date_debut'],
        ], [
            'libelle.regex'  => "Le libellé s'écrit AAAA-AAAA, par exemple 2026-2027.",
            'libelle.unique' => 'Cette année existe déjà.',
            'date_fin.after' => 'La fin doit venir après le début.',
        ]);

        [$premiere, $seconde] = array_map('intval', explode('-', $valeurs['libelle']));

        if ($seconde !== $premiere + 1) {
            throw ValidationException::withMessages(['libelle' => "Les deux années se suivent : {$premiere}-" . ($premiere + 1) . '.']);
        }

        if ((int) substr($valeurs['date_debut'], 0, 4) !== $premiere) {
            throw ValidationException::withMessages(['date_debut' => "L'année {$valeurs['libelle']} commence en {$premiere}."]);
        }

        if ((int) substr($valeurs['date_fin'], 0, 4) !== $seconde) {
            throw ValidationException::withMessages(['date_fin' => "L'année {$valeurs['libelle']} se termine en {$seconde}."]);
        }

        $chevauchee = AnneeAcademique::query()
            ->when($sauf, fn ($q) => $q->where('id', '!=', $sauf->id))
            ->whereDate('date_debut', '<=', $valeurs['date_fin'])
            ->whereDate('date_fin', '>=', $valeurs['date_debut'])
            ->first();

        if ($chevauchee) {
            throw ValidationException::withMessages([
                'date_debut' => "Ces dates chevauchent {$chevauchee->libelle} (du {$chevauchee->date_debut->format('d/m/Y')} au {$chevauchee->date_fin->format('d/m/Y')}).",
            ]);
        }

        return $valeurs;
    }
}
