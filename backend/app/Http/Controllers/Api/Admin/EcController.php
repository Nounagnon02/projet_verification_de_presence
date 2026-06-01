<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcController extends Controller
{
    public function index(): JsonResponse
    {
        $ecs = Ec::with('ue.filiere')->orderBy('code')->get();
        return $this->successResponse($ecs);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ue_id'          => 'required|exists:ues,id',
            'code'           => 'required|string|max:20|unique:ecs,code',
            'intitule'       => 'required|string|max:255',
            'volume_horaire' => 'required|integer|min:1',
        ]);

        $ec = Ec::create($validated);
        $ec->load('ue.filiere');
        return $this->createdResponse($ec, 'EC créé avec succès.');
    }

    public function update(Request $request, Ec $ec): JsonResponse
    {
        $validated = $request->validate([
            'ue_id'          => 'sometimes|exists:ues,id',
            'code'           => 'sometimes|string|max:20|unique:ecs,code,' . $ec->id,
            'intitule'       => 'sometimes|string|max:255',
            'volume_horaire' => 'sometimes|integer|min:1',
        ]);

        $ec->update($validated);
        $ec->load('ue.filiere');
        return $this->successResponse($ec, 'EC mis à jour.');
    }

    public function destroy(Ec $ec): JsonResponse
    {
        $ec->delete();
        return $this->successResponse(null, 'EC supprimé.');
    }
}
