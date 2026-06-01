<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnneeAcademiqueController extends Controller
{
    public function index(): JsonResponse
    {
        $annees = AnneeAcademique::orderBy('date_debut', 'desc')->get();
        return $this->successResponse($annees);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'libelle'    => 'required|string|max:20|unique:annees_academiques,libelle',
            'date_debut' => 'required|date',
            'date_fin'   => 'required|date|after:date_debut',
            'active'     => 'boolean',
        ]);

        if ($request->boolean('active')) {
            AnneeAcademique::where('active', true)->update(['active' => false]);
        }

        $annee = AnneeAcademique::create($validated);
        return $this->createdResponse($annee, 'Année académique créée avec succès.');
    }

    public function show(AnneeAcademique $anneeAcademique): JsonResponse
    {
        return $this->successResponse($anneeAcademique);
    }

    public function update(Request $request, AnneeAcademique $anneeAcademique): JsonResponse
    {
        $validated = $request->validate([
            'libelle'    => 'sometimes|string|max:20|unique:annees_academiques,libelle,' . $anneeAcademique->id,
            'date_debut' => 'sometimes|date',
            'date_fin'   => 'sometimes|date|after:date_debut',
            'active'     => 'boolean',
        ]);

        if ($request->boolean('active')) {
            AnneeAcademique::where('active', true)->update(['active' => false]);
        }

        $anneeAcademique->update($validated);
        return $this->successResponse($anneeAcademique, 'Année académique mise à jour.');
    }

    public function activate(AnneeAcademique $anneeAcademique): JsonResponse
    {
        AnneeAcademique::where('active', true)->update(['active' => false]);
        $anneeAcademique->update(['active' => true]);

        return $this->successResponse($anneeAcademique, 'Année académique activée.');
    }

    public function destroy(AnneeAcademique $anneeAcademique): JsonResponse
    {
        $anneeAcademique->delete();
        return $this->successResponse(null, 'Année académique supprimée.');
    }
}
