<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Anomaly;
use App\Models\Presence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * Liste des alertes de fraude potentielle (CDC 11.1).
     * Retourne les anomalies de fraude (double scan, device mismatch, etc.)
     * GET /api/admin/alerts
     */
    public function index(): JsonResponse
    {
        $alerts = Anomaly::with(['etudiant'])
            ->where('resolved', false)
            ->latest()
            ->paginate(15);

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
