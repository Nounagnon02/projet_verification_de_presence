<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use App\Models\Salle;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalleController extends Controller
{
    use ScopedByEtablissement;

    public function index(Request $request): JsonResponse
    {
        $query = Salle::with('etablissement');

        // Scope par établissement
        $this->scopeQuery($query, $request);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        $salles = $query->orderBy('nom')->get();

        return $this->successResponse($salles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'              => 'required|string|max:255',
            'code'             => 'required|string|max:50|unique:salles,code',
            'etablissement_id' => 'required|exists:etablissements,id',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',
            'rayon_geofence_m' => 'nullable|integer|min:5|max:500',
            'ssid_attendu'     => 'nullable|string|max:255',
            'bssid_attendu'    => 'nullable|string|max:17',
            'ip_range'         => 'nullable|string|max:50',
            'hors_reseau'      => 'boolean',
            'actif'            => 'boolean',
        ]);

        // Vérifier scoping établissement
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId && (int) $validated['etablissement_id'] !== $etablissementId) {
            return $this->errorResponse('Vous ne pouvez créer une salle que pour votre établissement.', 403);
        }

        $salle = Salle::create($validated);

        return $this->createdResponse($salle, 'Salle créée avec succès.');
    }

    public function show(Request $request, Salle $salle): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId && $salle->etablissement_id !== $etablissementId) {
            return $this->errorResponse('Salle non trouvée.', 404);
        }

        $salle->load(['etablissement', 'evenements' => fn ($q) => $q->with('ec')->orderBy('date', 'desc')->limit(10)]);

        return $this->successResponse($salle);
    }

    public function update(Request $request, Salle $salle): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId && $salle->etablissement_id !== $etablissementId) {
            return $this->errorResponse('Salle non trouvée.', 404);
        }

        $validated = $request->validate([
            'nom'              => 'sometimes|string|max:255',
            'code'             => 'sometimes|string|max:50|unique:salles,code,' . $salle->id,
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',
            'rayon_geofence_m' => 'nullable|integer|min:5|max:500',
            'ssid_attendu'     => 'nullable|string|max:255',
            'bssid_attendu'    => 'nullable|string|max:17',
            'ip_range'         => 'nullable|string|max:50',
            'hors_reseau'      => 'boolean',
            'actif'            => 'boolean',
        ]);

        $salle->update($validated);

        return $this->successResponse($salle, 'Salle mise à jour.');
    }

    public function destroy(Request $request, Salle $salle): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId && $salle->etablissement_id !== $etablissementId) {
            return $this->errorResponse('Salle non trouvée.', 404);
        }

        // Vérifier qu'aucun événement futur n'utilise cette salle
        $evenementsFuturs = $salle->evenements()->where('date', '>=', now())->count();
        if ($evenementsFuturs > 0) {
            return $this->errorResponse(
                "Impossible de supprimer : {$evenementsFuturs} événement(s) futur(s) utilise(nt) cette salle.",
                422
            );
        }

        $salle->delete();

        return $this->successResponse(null, 'Salle supprimée.');
    }

    /**
     * Obtenir les salles disponibles pour un établissement (pour select dans les formulaires).
     */
    public function disponibles(Request $request): JsonResponse
    {
        $query = Salle::where('actif', true);

        $this->scopeQuery($query, $request);

        $salles = $query->select('id', 'nom', 'code', 'etablissement_id')
            ->orderBy('nom')
            ->get();

        return $this->successResponse($salles);
    }
}
