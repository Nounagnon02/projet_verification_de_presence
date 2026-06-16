<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvenementController extends Controller
{
    use ScopedByEtablissement;

    public function index(Request $request): JsonResponse
    {
        $query = Evenement::with(['ec.ue', 'filiere', 'presences', 'qrCode', 'salleRef']);

        // Scope par établissement via la filière
        $this->scopeViaRelation($query, $request, 'filiere');

        if ($request->filled('date_debut')) {
            $query->where('date', '>=', $request->date_debut);
        }
        if ($request->filled('date_fin')) {
            $query->where('date', '<=', $request->date_fin);
        }
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }
        if ($request->filled('filiere_id')) {
            $query->where('filiere_id', $request->filiere_id);
        }
        if ($request->filled('annee_id')) {
            $query->where('annee_id', $request->annee_id);
        }
        if ($request->filled('semestre')) {
            $query->whereHas('ec.ue', fn($q) => $q->where('semestre', $request->semestre));
        }
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        $evenements = $query->orderBy('date', 'asc')
            ->orderBy('heure_debut', 'asc')
            ->get()
            ->map(fn($e) => [
                'id'             => $e->id,
                'date'           => $e->date->format('Y-m-d'),
                'heure_debut'    => $e->heure_debut,
                'heure_fin'      => $e->heure_fin,
                'salle'          => $e->salle,
                'salle_id'       => $e->salle_id,
                'salle_ref'      => $e->salleRef ? [
                    'id'     => $e->salleRef->id,
                    'nom'    => $e->salleRef->nom,
                    'code'   => $e->salleRef->code,
                ] : null,
                'statut'         => $e->statut,
                'ec'             => $e->ec ? ['id' => $e->ec->id, 'code' => $e->ec->code, 'intitule' => $e->ec->intitule] : null,
                'ue'             => $e->ec && $e->ec->ue ? ['id' => $e->ec->ue->id, 'code' => $e->ec->ue->code] : null,
                'filiere'        => $e->filiere ? ['id' => $e->filiere->id, 'code' => $e->filiere->code] : null,
                'presences_count' => $e->presences->count(),
                'has_qr_code'    => $e->qrCode ? true : false,
                'qr_code'        => $e->qrCode ? [
                    'id'         => $e->qrCode->id,
                    'token'      => $e->qrCode->token,
                    'expire_at'  => $e->qrCode->expire_at?->format('Y-m-d H:i:s'),
                    'actif'      => $e->qrCode->actif,
                    'is_expired' => $e->qrCode->isExpired(),
                ] : null,
            ]);

        return $this->successResponse($evenements);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ec_id'       => 'required|exists:ecs,id',
            'filiere_id'  => 'required|exists:filieres,id',
            'annee_id'    => 'required|exists:annees_academiques,id',
            'date'        => 'required|date',
            'heure_debut' => 'required|date_format:H:i',
            'heure_fin'   => 'required|date_format:H:i|after:heure_debut',
            'salle'       => 'nullable|string|max:100',
            'salle_id'    => 'nullable|exists:salles,id',
            'statut'      => 'sometimes|string|in:planifie,en_cours,termine,annule',
        ]);

        $evenement = Evenement::create($validated);
        return $this->createdResponse($evenement, 'Événement créé avec succès.');
    }

    public function show(Request $request, Evenement $evenement): JsonResponse
    {
        // Vérifier que l'admin a accès à cet événement (scope établissement)
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId && $evenement->filiere?->etablissement_id !== $etablissementId) {
            return $this->errorResponse('Événement non trouvé.', 404);
        }

        $evenement->load(['ec.ue', 'filiere', 'presences.etudiant', 'qrCode', 'salleRef']);
        return $this->successResponse($evenement);
    }

    public function update(Request $request, Evenement $evenement): JsonResponse
    {
        // Vérifier que l'admin a accès à cet événement (scope établissement)
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId && $evenement->filiere?->etablissement_id !== $etablissementId) {
            return $this->errorResponse('Événement non trouvé.', 404);
        }

        $validated = $request->validate([
            'ec_id'       => 'sometimes|exists:ecs,id',
            'filiere_id'  => 'sometimes|exists:filieres,id',
            'annee_id'    => 'sometimes|exists:annees_academiques,id',
            'date'        => 'sometimes|date',
            'heure_debut' => 'sometimes|date_format:H:i',
            'heure_fin'   => 'sometimes|date_format:H:i|after:heure_debut',
            'salle'       => 'nullable|string|max:100',
            'salle_id'    => 'nullable|exists:salles,id',
            'statut'      => 'sometimes|string|in:planifie,en_cours,termine,annule',
        ]);

        $evenement->update($validated);
        return $this->successResponse($evenement, 'Événement mis à jour.');
    }

    public function destroy(Request $request, Evenement $evenement): JsonResponse
    {
        // Vérifier que l'admin a accès à cet événement (scope établissement)
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId && $evenement->filiere?->etablissement_id !== $etablissementId) {
            return $this->errorResponse('Événement non trouvé.', 404);
        }

        $evenement->delete();
        return $this->successResponse(null, 'Événement supprimé.');
    }
}
