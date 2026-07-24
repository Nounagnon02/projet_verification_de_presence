<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
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

        // Lier automatiquement à l'année académique active
        $activeAnnee = AnneeAcademique::where('active', true)->first();
        if ($activeAnnee) {
            $filiere->anneesAcademiques()->syncWithoutDetaching([$activeAnnee->id]);
        }

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

    /**
     * Reconduire les filières d'une année source vers une année cible.
     */
    public function reconduire(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_annee_id' => 'required|exists:annees_academiques,id',
            'target_annee_id' => 'required|exists:annees_academiques,id|different:source_annee_id',
        ]);

        $sourceAnnee = AnneeAcademique::findOrFail($validated['source_annee_id']);
        $targetAnnee = AnneeAcademique::findOrFail($validated['target_annee_id']);

        $filiereIds = $sourceAnnee->filieres()->pluck('filieres.id')->toArray();
        $targetAnnee->filieres()->syncWithoutDetaching($filiereIds);

        $count = count($filiereIds);
        return $this->successResponse([
            'reconduites' => $count,
            'source'       => $sourceAnnee->libelle,
            'target'       => $targetAnnee->libelle,
        ], "{$count} filière(s) reconduite(s) de {$sourceAnnee->libelle} vers {$targetAnnee->libelle}");
    }
}
