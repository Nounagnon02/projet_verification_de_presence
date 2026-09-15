<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Services\BasculeAnnee;
use App\Services\PreparationAnnee;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Années académiques, côté établissement : les consulter, et choisir celle sur
 * laquelle l'établissement travaille.
 *
 * Les années sont communes à l'université et créées par le super
 * administrateur (SuperAdmin\AnneeUniversitaireController). Chaque faculté
 * créait les siennes : dix facultés auraient eu dix « 2025-2026 ».
 */
class AnneeAcademiqueController extends Controller
{
    use ScopedByEtablissement;

    public function __construct(
        private readonly BasculeAnnee $bascule,
        private readonly PreparationAnnee $preparation,
    ) {}

    /**
     * Ce que préparer cette année copierait, filière par filière.
     *
     * GET /api/admin/annees-academiques/{anneeAcademique}/preparation?source=
     */
    public function preparation(Request $request, AnneeAcademique $anneeAcademique): JsonResponse
    {
        [$etablissementId, $source] = $this->cadrePreparation($request, $anneeAcademique);

        return $this->successResponse([
            'source'   => ['id' => $source->id, 'libelle' => $source->libelle],
            'cible'    => ['id' => $anneeAcademique->id, 'libelle' => $anneeAcademique->libelle],
            'filieres' => $this->preparation->apercu($source, $anneeAcademique, $etablissementId),
        ]);
    }

    /**
     * Copie filières, maquette et emploi du temps de la source vers cette année.
     *
     * POST /api/admin/annees-academiques/{anneeAcademique}/preparer
     */
    public function preparer(Request $request, AnneeAcademique $anneeAcademique): JsonResponse
    {
        [$etablissementId, $source] = $this->cadrePreparation($request, $anneeAcademique);
        $this->refuserSiAnneeClose($anneeAcademique, $request);

        $valeurs = $request->validate([
            'filiere_ids'   => ['required', 'array', 'min:1'],
            'filiere_ids.*' => ['integer'],
            'avec_edt'      => ['sometimes', 'boolean'],
        ], ['filiere_ids.required' => 'Choisissez au moins une filière.']);

        $connues = $this->preparation->filieres($source, $etablissementId)->pluck('id')->all();
        $inconnues = array_diff(array_map('intval', $valeurs['filiere_ids']), $connues);

        if ($inconnues !== []) {
            return $this->errorResponse("Filière(s) absente(s) de {$source->libelle} dans votre établissement : " . implode(', ', $inconnues) . '.', 422);
        }

        $bilan = $this->preparation->preparer($source, $anneeAcademique, $etablissementId, $valeurs['filiere_ids'], $request->boolean('avec_edt', true));

        $total = fn (string $cle) => array_sum(array_column($bilan, $cle));

        return $this->successResponse(
            ['source' => $source->libelle, 'cible' => $anneeAcademique->libelle, 'filieres' => $bilan],
            count($bilan) . " filière(s) préparée(s) pour {$anneeAcademique->libelle} : {$total('ues')} UE, {$total('ecs')} EC, {$total('creneaux')} créneau(x) d'emploi du temps."
        );
    }

    /**
     * Établissement et année source d'une préparation. La source par défaut
     * est l'année active de l'établissement ; elle précède toujours la cible.
     *
     * @return array{0: int, 1: AnneeAcademique}
     */
    private function cadrePreparation(Request $request, AnneeAcademique $cible): array
    {
        $etablissementId = $this->getEtablissementId($request);

        if (!$etablissementId) {
            abort(422, "La préparation d'une année se fait depuis l'espace d'un établissement.");
        }

        $sourceId = $request->input('source_annee_id') ?? $request->query('source');
        $source = $sourceId ? AnneeAcademique::find($sourceId) : AnneeAcademique::activePour($etablissementId);

        if (!$source) {
            abort(422, 'Année source introuvable.');
        }

        if ($source->date_debut->gte($cible->date_debut)) {
            abort(422, "On prépare une année à partir d'une année antérieure : {$source->libelle} ne précède pas {$cible->libelle}.");
        }

        return [$etablissementId, $source];
    }

    public function index(Request $request): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);
        $active = AnneeAcademique::activePour($etablissementId);

        // Ce que chaque année contient POUR CET ÉTABLISSEMENT.
        $chezNous = function ($q) use ($etablissementId) {
            if ($etablissementId) {
                $q->whereHas('filiere', fn ($f) => $f->where('etablissement_id', $etablissementId));
            }
        };

        $annees = AnneeAcademique::withCount([
            'evenements'     => $chezNous,
            'ues'            => $chezNous,
            'emploisDuTemps' => $chezNous,
        ])->avecEffectifs($etablissementId)->orderBy('date_debut', 'desc')->get()
            ->each(function (AnneeAcademique $annee) use ($active, $etablissementId) {
                $estActive = $active !== null && $active->id === $annee->id;

                // « active » : l'année de CET établissement. Les écrans le lisent
                // pour ouvrir leurs filtres et pour les inscriptions ; l'année en
                // cours de l'université est donnée à part.
                $annee->setAttribute('en_cours_universite', (bool) $annee->active);
                $annee->setAttribute('active', $estActive);
                $annee->setAttribute('statut', $annee->statut());
                // Antérieure à l'année active : consultation seulement.
                $annee->setAttribute('close', $active !== null && $annee->date_debut->lt($active->date_debut));

                // Ce qu'un changement d'année retirerait : la fenêtre de
                // confirmation l'annonce.
                if ($estActive && $etablissementId) {
                    $annee->setAttribute('seances_a_venir_count', $this->bascule->seancesAVenir($annee->id, collect([$etablissementId]))->count());
                }
            });

        return $this->successResponse($annees);
    }

    public function show(AnneeAcademique $anneeAcademique): JsonResponse
    {
        return $this->successResponse($anneeAcademique);
    }

    /**
     * L'établissement bascule sur cette année. Les autres établissements ne
     * bougent pas : on désactivait toutes les autres années, et un super
     * administrateur changeait ainsi l'année de toutes les facultés d'un coup.
     *
     * PATCH /api/admin/annees-academiques/{anneeAcademique}/activate
     */
    public function activate(Request $request, AnneeAcademique $anneeAcademique): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);

        if (!$etablissementId) {
            return $this->errorResponse("L'année en cours de l'université se définit dans l'espace super administrateur.", 422);
        }

        $retirees = $this->bascule->basculer($etablissementId, $anneeAcademique);

        $message = "{$anneeAcademique->libelle} est désormais l'année active de votre établissement.";
        if ($retirees > 0) {
            $message .= " {$retirees} séance(s) déjà planifiée(s) de l'année précédente ont été retirées.";
        }

        return $this->successResponse(
            ['id' => $anneeAcademique->id, 'libelle' => $anneeAcademique->libelle, 'active' => true, 'seances_retirees' => $retirees],
            $message
        );
    }
}
