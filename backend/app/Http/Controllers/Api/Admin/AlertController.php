<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Presence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * Liste des alertes de fraude potentielle (CDC 11.1).
     * GET /api/admin/alerts
     */
    public function index(): JsonResponse
    {
        $alerts = Presence::with(['etudiant', 'evenement.ec'])
            ->where('statut', 'suspect')
            ->latest()
            ->paginate(15);

        return $this->paginatedResponse($alerts);
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

        $presence = Presence::findOrFail($id);
        $presence->update(['statut' => $request->status]);

        return $this->successResponse(
            ['presence' => $presence],
            'Alerte résolue avec succès.'
        );
    }
}
