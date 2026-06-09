<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Filiere;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiliereController extends Controller
{
    use ScopedByEtablissement;

    public function index(Request $request): JsonResponse
    {
        $query = Filiere::withCount(['etudiants', 'ues']);

        // Scope par établissement pour les admins faculté
        $this->scopeQuery($query, $request);

        $filieres = $query->orderBy('intitule')->get();

        return $this->successResponse($filieres);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'     => 'required|string|max:10|unique:filieres,code',
            'intitule' => 'required|string|max:255',
            'niveau'   => 'required|string|max:10',
        ]);

        // Assigner automatiquement l'établissement scope
        $validated['etablissement_id'] = $this->getEtablissementId($request);

        $filiere = Filiere::create($validated);
        $filiere->loadCount(['etudiants', 'ues']);
        return $this->createdResponse($filiere, 'Filière créée avec succès.');
    }

    public function show(Filiere $filiere): JsonResponse
    {
        $filiere->loadCount(['etudiants', 'ues']);
        $filiere->load('ues.ecs');
        return $this->successResponse($filiere);
    }

    public function update(Request $request, Filiere $filiere): JsonResponse
    {
        $validated = $request->validate([
            'code'     => 'sometimes|string|max:10|unique:filieres,code,' . $filiere->id,
            'intitule' => 'sometimes|string|max:255',
            'niveau'   => 'sometimes|string|max:10',
        ]);

        // Empêcher un admin faculté de changer l'établissement
        if ($this->getEtablissementId($request)) {
            unset($validated['etablissement_id']);
        }

        $filiere->update($validated);
        $filiere->loadCount(['etudiants', 'ues']);
        return $this->successResponse($filiere, 'Filière mise à jour.');
    }

    public function destroy(Filiere $filiere): JsonResponse
    {
        $filiere->delete();
        return $this->successResponse(null, 'Filière supprimée.');
    }
}
