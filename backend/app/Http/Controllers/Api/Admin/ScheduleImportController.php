<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\EmploiDuTemps;
use App\Models\Filiere;
use App\Services\Schedule\NormalisateurCreneau;
use App\Services\Schedule\ValidateurCreneau;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Import d'un emploi du temps : vérification, puis persistance.
 *
 * POURQUOI DEUX ENDPOINTS SÉPARÉS
 *
 * L'ancien chemin — ImportController::validateEvents — validait et enregistrait
 * dans le même appel, avec « exists:ecs,id » pour seule vérification de
 * cohérence. Un fichier partiellement faux polluait donc la base :
 *
 *   - la boucle créait les événements un par un, hors transaction : une erreur
 *     au dixième laissait les neuf premiers en base ;
 *   - aucun conflit n'était cherché côté serveur ;
 *   - un EC d'une autre filière passait sans un mot.
 *
 * La vérification est désormais un appel SANS effet de bord, qui rend un rapport
 * ligne par ligne. La persistance est un second appel, qui REVALIDE tout — le
 * rapport du client n'est jamais une autorisation — et écrit en une transaction :
 * tout passe, ou rien.
 */
class ScheduleImportController extends Controller
{
    use ScopedByEtablissement;

    public function __construct(
        private readonly NormalisateurCreneau $normalisateur = new NormalisateurCreneau(),
        private readonly ValidateurCreneau $validateur = new ValidateurCreneau(),
    ) {}

    /**
     * Vérifie un lot de créneaux sans rien enregistrer.
     *
     * POST /api/admin/import/schedule/verifier
     */
    public function verifier(Request $request): JsonResponse
    {
        $validated = $this->validerLaRequete($request);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $rapport = $this->examiner($validated);

        return $this->successResponse($rapport, $this->resume($rapport));
    }

    /**
     * Enregistre un lot de créneaux, après l'avoir revalidé.
     *
     * POST /api/admin/import/schedule/confirmer
     */
    public function confirmer(Request $request): JsonResponse
    {
        $validated = $this->validerLaRequete($request);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $this->refuserSiAnneeClose((int) $validated['annee_id'], $request);

        // On REVALIDE : le rapport rendu au client n'est pas une autorisation.
        // Entre la vérification et la confirmation, la base a pu changer — un
        // autre administrateur peut avoir occupé la salle.
        $rapport = $this->examiner($validated);

        $refuses = array_values(array_filter(
            $rapport['lignes'],
            fn (array $l) => $l['statut'] !== ValidateurCreneau::VALIDE
        ));

        $ignorer = $request->boolean('ignorer_les_refuses');

        if ($refuses !== [] && !$ignorer) {
            // Tout ou rien par défaut : un fichier partiellement faux ne doit pas
            // laisser la moitié de son contenu en base.
            return response()->json([
                'success' => false,
                'message' => count($refuses) . ' créneau(x) sur ' . count($rapport['lignes'])
                    . ' ne peuvent pas être enregistrés. Rien n\'a été écrit. '
                    . 'Corrigez-les, ou demandez explicitement à ignorer les lignes refusées.',
                'data'    => $rapport,
            ], 422);
        }

        $aEcrire = array_values(array_filter(
            $rapport['lignes'],
            fn (array $l) => $l['statut'] === ValidateurCreneau::VALIDE
        ));

        if ($aEcrire === []) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun créneau valide à enregistrer.',
                'data'    => $rapport,
            ], 422);
        }

        $crees = DB::transaction(function () use ($aEcrire, $validated) {
            $crees = [];

            foreach ($aEcrire as $ligne) {
                $c = $ligne['creneau'];

                $crees[] = EmploiDuTemps::create([
                    'ec_id'         => $c['ec_id'],
                    'filiere_id'    => $validated['filiere_id'],
                    'annee_id'      => $validated['annee_id'],
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
                ])->id;
            }

            return $crees;
        });

        Log::info('Emploi du temps importé', [
            'filiere_id' => $validated['filiere_id'],
            'annee_id'   => $validated['annee_id'],
            'enregistres' => count($crees),
            'refuses'     => count($refuses),
            'ignores'     => $ignorer,
        ]);

        return $this->createdResponse([
            'enregistres' => count($crees),
            'refuses'     => count($refuses),
            'rapport'     => $rapport,
        ], count($crees) . ' créneau(x) enregistré(s) à l\'emploi du temps.'
            . ($refuses !== [] ? ' ' . count($refuses) . ' ligne(s) ignorée(s).' : ''));
    }

    /**
     * Validation de la requête et du cloisonnement par établissement.
     *
     * @return array{creneaux: list<array<string, mixed>>, filiere_id: int, annee_id: int, valide_du: ?string, valide_au: ?string}|JsonResponse
     */
    private function validerLaRequete(Request $request): array|JsonResponse
    {
        $validated = $request->validate([
            'creneaux'   => 'required|array|min:1',
            'creneaux.*' => 'array',
            'filiere_id' => 'required|integer|exists:filieres,id',
            'annee_id'   => 'required|integer|exists:annees_academiques,id',
            // Version du document : « emploi du temps à partir du 15 juin ».
            'valide_du'  => 'nullable|date_format:Y-m-d',
            'valide_au'  => 'nullable|date_format:Y-m-d|after_or_equal:valide_du',
        ]);

        $etablissementId = $this->getEtablissementId($request);

        if ($etablissementId) {
            $filiere = Filiere::find($validated['filiere_id']);

            if ($filiere && $filiere->etablissement_id !== (int) $etablissementId) {
                return response()->json([
                    'success' => false,
                    'message' => "Cette filière n'appartient pas à votre établissement.",
                ], 403);
            }
        }

        return [
            'creneaux'   => $validated['creneaux'],
            'filiere_id' => (int) $validated['filiere_id'],
            'annee_id'   => (int) $validated['annee_id'],
            'valide_du'  => $validated['valide_du'] ?? null,
            'valide_au'  => $validated['valide_au'] ?? null,
        ];
    }

    /**
     * Normalise puis valide chaque créneau. Aucun effet de bord.
     *
     * La validité du document vaut pour chaque créneau qui n'en porte pas.
     *
     * @param  array{creneaux: list<array<string, mixed>>, filiere_id: int, annee_id: int, valide_du: ?string, valide_au: ?string} $validated
     * @return array<string, mixed>
     */
    private function examiner(array $validated): array
    {
        $bruts = array_map(
            fn ($brut) => is_array($brut) ? $brut + array_filter(['valide_du' => $validated['valide_du'], 'valide_au' => $validated['valide_au']]) : $brut,
            $validated['creneaux']
        );
        $preparation = $this->validateur->preparer($validated['filiere_id'], $validated['annee_id']);

        if (!$preparation['ok']) {
            return ['lignes' => [], 'compteurs' => [], 'erreur' => $preparation['motif']];
        }

        $lignes = [];

        foreach ($bruts as $rang => $brut) {
            $n = $this->normalisateur->normaliser(is_array($brut) ? $brut : []);

            if (!$n['ok']) {
                $lignes[] = [
                    'rang'    => $rang + 1,
                    'statut'  => ValidateurCreneau::INVALIDE,
                    'motifs'  => [$n['motif'] ?? 'Créneau non interprétable.'],
                    'champ'   => $n['champ'] ?? null,
                    'source'  => $brut,
                    'creneau' => null,
                ];
                continue;
            }

            $v = $this->validateur->valider($n['creneau']);

            $lignes[] = [
                'rang'    => $rang + 1,
                'statut'  => $v['statut'],
                'motifs'  => $v['motifs'],
                'champ'   => null,
                'source'  => $brut,
                'creneau' => $v['creneau'] ?? null,
            ];
        }

        $compteurs = [];

        foreach ($lignes as $ligne) {
            $compteurs[$ligne['statut']] = ($compteurs[$ligne['statut']] ?? 0) + 1;
        }

        return [
            'lignes'    => $lignes,
            'compteurs' => $compteurs,
            'total'     => count($lignes),
            'valides'   => $compteurs[ValidateurCreneau::VALIDE] ?? 0,
        ];
    }

    /** @param array<string, mixed> $rapport */
    private function resume(array $rapport): string
    {
        if (isset($rapport['erreur'])) {
            return $rapport['erreur'];
        }

        $valides = $rapport['valides'];
        $total   = $rapport['total'];

        if ($valides === $total) {
            return "{$total} créneau(x) vérifié(s), tous enregistrables.";
        }

        return "{$valides} créneau(x) sur {$total} sont enregistrables. "
            . 'Les autres sont détaillés avec leur motif.';
    }
}
