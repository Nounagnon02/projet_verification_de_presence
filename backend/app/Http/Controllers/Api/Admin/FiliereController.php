<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Filiere;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiliereController extends Controller
{
    public function index(): JsonResponse
    {
        $filieres = Filiere::withCount(['etudiants', 'ues'])->orderBy('intitule')->get();
        return $this->successResponse($filieres);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'    => 'required|string|max:10|unique:filieres,code',
            'intitule' => 'required|string|max:255',
            'niveau'  => 'required|string|max:10',
        ]);

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
