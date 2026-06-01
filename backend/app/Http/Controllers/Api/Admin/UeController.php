<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UeResource;
use App\Models\Ue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $ues = Ue::with(['filiere', 'ecs'])->withCount('ecs')->orderBy('code')->get();
        return UeResource::collection($ues);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'          => 'required|string|max:20|unique:ues,code',
            'intitule'      => 'required|string|max:255',
            'filiere_id'    => 'required|exists:filieres,id',
            'annee_id'      => 'required|exists:annees_academiques,id',
            'semestre'      => 'required|integer|min:1|max:6',
            'volume_horaire' => 'required|integer|min:1',
        ]);

        $ue = Ue::create($validated);
        $ue->load(['filiere', 'ecs']);
        return $this->createdResponse(new UeResource($ue), 'UE créée avec succès.');
    }

    public function show(Ue $ue): UeResource
    {
        $ue->load(['filiere', 'ecs.evenements']);
        return new UeResource($ue);
    }

    public function update(Request $request, Ue $ue): JsonResponse
    {
        $validated = $request->validate([
            'code'          => 'sometimes|string|max:20|unique:ues,code,' . $ue->id,
            'intitule'      => 'sometimes|string|max:255',
            'filiere_id'    => 'sometimes|exists:filieres,id',
            'annee_id'      => 'sometimes|exists:annees_academiques,id',
            'semestre'      => 'sometimes|integer|min:1|max:6',
            'volume_horaire' => 'sometimes|integer|min:1',
        ]);

        $ue->update($validated);
        $ue->load(['filiere', 'ecs']);
        return $this->successResponse(new UeResource($ue), 'UE mise à jour.');
    }

    public function destroy(Ue $ue): JsonResponse
    {
        $ue->delete();
        return $this->successResponse(null, 'UE supprimée.');
    }
}
