<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Anomaly;
use App\Models\Presence;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    use ScopedByEtablissement;

    /**
     * Liste des alertes de fraude potentielle (CDC 11.1).
     * Retourne les anomalies de fraude (double scan, device mismatch, etc.)
     * GET /api/admin/alerts
     */
    public function index(Request $request): JsonResponse
    {
        $query = Anomaly::with(['etudiant'])->where('resolved', false);

        // Cloisonnement : un admin de faculté ne voit que les anomalies de ses
        // propres étudiants (via filiere.etablissement_id). Sans ce filtre, les
        // noms/matricules des étudiants des autres facultés fuitaient.
        if ($etablissementId = $this->getEtablissementId($request)) {
            $query->whereHas('etudiant.filiere', fn ($q) => $q->where('etablissement_id', $etablissementId));
        }

        $alerts = $query->latest()->paginate(15);

        $data = $alerts->map(fn($a) => [
            'id'          => $a->id,
            'type'        => $a->type,
            'description' => $a->description,
            'severite'    => $a->severity,
            'etudiant'    => $a->etudiant ? [
                'id'       => $a->etudiant->id,
                'nom'      => $a->etudiant->nom,
                'prenom'   => $a->etudiant->prenom,
                'matricule'=> $a->etudiant->matricule,
            ] : null,
            'resolved'    => $a->resolved,
            'creee_le'    => $a->created_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Liste des alertes récupérée.',
            'data'    => $data,
            'meta'    => [
                'current_page' => $alerts->currentPage(),
                'last_page'    => $alerts->lastPage(),
                'per_page'     => $alerts->perPage(),
                'total'        => $alerts->total(),
            ],
        ]);
    }

    /**
     * Valider ou invalider une alerte (CDC 9.2.2).
     * POST /api/admin/alerts/{id}/resolve
     */
    public function resolve(Request $request, int $id): JsonResponse
    {
        $validator = validator($request->all(), [
            'status' => 'required|in:valide,invalide',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $anomaly = Anomaly::findOrFail($id);

        // Empêche un admin de faculté de résoudre — et de restaurer une présence —
        // sur une anomalie d'une autre faculté.
        if ($etablissementId = $this->getEtablissementId($request)) {
            $appartient = $anomaly->etudiant
                && (int) optional($anomaly->etudiant->filiere)->etablissement_id === (int) $etablissementId;
            if (!$appartient) {
                return $this->errorResponse('Anomalie non trouvée.', 404);
            }
        }

        $anomaly->update([
            'resolved'    => true,
            'resolved_at' => now(),
        ]);

        // Si l'anomalie est liée à une présence suspecte, on la restaure
        if ($request->status === 'valide' && $anomaly->metadata) {
            $presenceId = $anomaly->metadata['premiere_presence_id'] ?? null;
            if ($presenceId) {
                Presence::where('id', $presenceId)->update(['statut' => 'valide']);
            }
        }

        return $this->successResponse(
            ['anomalie' => $anomaly->fresh()],
            'Alerte résolue avec succès.'
        );
    }
}
