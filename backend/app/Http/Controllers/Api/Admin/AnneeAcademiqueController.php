<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnneeAcademiqueController extends Controller
{
    use ScopedByEtablissement;

    public function index(Request $request): JsonResponse
    {
        $query = AnneeAcademique::orderBy('date_debut', 'desc');
        $this->scopeQuery($query, $request);
        $annees = $query->get();
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

        // Assigner automatiquement l'établissement scope
        $validated['etablissement_id'] = $this->getEtablissementId($request);

        if ($request->boolean('active')) {
            $query = AnneeAcademique::query();
            $this->scopeQuery($query, $request);
            $query->update(['active' => false]);
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

        // Empêcher un admin faculté de changer l'établissement
        if ($this->getEtablissementId($request)) {
            unset($validated['etablissement_id']);
        }

        if ($request->boolean('active')) {
            $query = AnneeAcademique::query();
            $this->scopeQuery($query, $request);
            $query->update(['active' => false]);
        }

        $anneeAcademique->update($validated);
        return $this->successResponse($anneeAcademique, 'Année académique mise à jour.');
    }

    public function activate(Request $request, AnneeAcademique $anneeAcademique): JsonResponse
    {
        $query = AnneeAcademique::query();
        $this->scopeQuery($query, $request);
        $query->where('active', true)->update(['active' => false]);

        $anneeAcademique->update(['active' => true]);
        return $this->successResponse($anneeAcademique, 'Année académique activée.');
    }

    public function destroy(AnneeAcademique $anneeAcademique): JsonResponse
    {
        $anneeAcademique->delete();
        return $this->successResponse(null, 'Année académique supprimée.');
    }
}
