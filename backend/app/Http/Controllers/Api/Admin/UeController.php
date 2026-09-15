<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UeResource;
use App\Models\Filiere;
use App\Models\Ue;
use App\Services\Maquette\RegistreMaquette;
use App\Traits\ScopedByEtablissement;
use App\Services\SemesterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UeController extends Controller
{
    use ScopedByEtablissement;

    public function __construct(private readonly RegistreMaquette $maquette) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Ue::with(['filiere', 'filieres:id,code,intitule,niveau', 'ecs'])->withCount('ecs');

        // Scope par établissement via la filière
        $this->scopeViaRelation($query, $request, 'filiere');

        if ($request->filled('annee_id')) {
            $query->where('annee_id', $request->annee_id);
        }
        if ($request->filled('filiere_id')) {
            // Les UE que suit la filière, cours communs compris.
            $query->whereHas('filieres', fn ($q) => $q->where('filieres.id', $request->filiere_id));
        }
        if ($niveau = $request->niveau) {
            $query->whereHas('filiere', fn($q) => $q->where('niveau', $niveau));
        }

        $ues = $query->orderBy('code')->get();
        // Avancement calculé depuis les séances terminées, pour toute la liste.
        app(\App\Services\AvancementCours::class)->appliquerAuxUes($ues);

        return UeResource::collection($ues);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'          => 'required|string|max:20',
            'intitule'      => 'required|string|max:255',
            'filiere_id'    => 'required|exists:filieres,id',
            'annee_id'      => 'required|exists:annees_academiques,id',
            // Jusqu'à 10 : la borne 6 rendait tout le Master impossible à saisir.
            'semestre'      => 'required|integer|min:1|max:10',
            // Déduit de ses EC (EcObserver) : il n'est plus saisi.
            'volume_horaire' => 'nullable|integer|min:0',
            'credits'       => 'nullable|integer|min:0|max:60',
            // Cours commun : les autres filières (même niveau) qui le suivent.
            'filiere_ids'   => 'sometimes|array',
            'filiere_ids.*' => 'integer|exists:filieres,id',
        ]);
        $validated['volume_horaire'] ??= 0;

        $filiere = Filiere::findOrFail($validated['filiere_id']);

        // La filière vient du client : un admin de faculté pouvait créer une UE
        // dans celle d'une autre faculté.
        $this->authorizeEtablissement($filiere, $request);

        $this->refuserSiAnneeClose((int) $validated['annee_id'], $request);
        $this->maquette->verifierCodeUe($validated['code'], $filiere, (int) $validated['annee_id']);
        $this->maquette->verifierSemestre((int) $validated['semestre'], $filiere);

        $ue = Ue::create(\Illuminate\Support\Arr::except($validated, ['filiere_ids']));
        $this->maquette->synchroniserFilieres($ue, $validated['filiere_ids'] ?? []);
        $ue->load(['filiere', 'filieres:id,code,intitule,niveau', 'ecs']);
        return $this->createdResponse(new UeResource($ue), 'UE créée avec succès.');
    }

    public function show(Request $request, Ue $ue): UeResource
    {
        $this->authorizeEtablissement($ue, $request, 'filiere');

        $ue->load(['filiere', 'ecs.evenements']);
        app(\App\Services\AvancementCours::class)->appliquerAuxUes([$ue]);

        return new UeResource($ue);
    }

    public function update(Request $request, Ue $ue): JsonResponse
    {
        $this->authorizeEtablissement($ue, $request, 'filiere');

        $validated = $request->validate([
            'code'          => 'sometimes|string|max:20',
            'intitule'      => 'sometimes|string|max:255',
            'filiere_id'    => 'sometimes|exists:filieres,id',
            'annee_id'      => 'sometimes|exists:annees_academiques,id',
            // Jusqu'à 10 : avec la borne 6, modifier une UE de Master échouait,
            // son semestre 7 étant renvoyé tel quel puis refusé.
            'semestre'      => 'sometimes|integer|min:1|max:10',
            'volume_horaire' => 'sometimes|nullable|integer|min:0',
            'credits'       => 'sometimes|nullable|integer|min:0|max:60',
            'filiere_ids'   => 'sometimes|array',
            'filiere_ids.*' => 'integer|exists:filieres,id',
        ]);
        unset($validated['volume_horaire']);

        $filiere = isset($validated['filiere_id']) ? Filiere::findOrFail($validated['filiere_id']) : $ue->filiere;

        if ((int) $filiere->id !== (int) $ue->filiere_id) {
            $this->authorizeEtablissement($filiere, $request);
        }

        $code = $validated['code'] ?? $ue->code;
        $anneeId = (int) ($validated['annee_id'] ?? $ue->annee_id);
        $this->refuserSiAnneeClose((int) $ue->annee_id, $request);
        $this->refuserSiAnneeClose($anneeId, $request);
        $semestre = (int) ($validated['semestre'] ?? $ue->semestre);

        if ($code !== $ue->code || (int) $filiere->id !== (int) $ue->filiere_id || $anneeId !== (int) $ue->annee_id) {
            $this->maquette->verifierCodeUe($code, $filiere, $anneeId, $ue->id);
        }

        // Contrôlé seulement si le semestre ou la filière change : modifier
        // l'intitulé d'une UE ancienne ne doit pas buter sur sa maquette.
        if ($semestre !== (int) $ue->semestre || (int) $filiere->id !== (int) $ue->filiere_id) {
            $this->maquette->verifierSemestre($semestre, $filiere);
        }

        $ue->update(\Illuminate\Support\Arr::except($validated, ['filiere_ids']));

        if (array_key_exists('filiere_ids', $validated)) {
            $this->maquette->synchroniserFilieres($ue->fresh(), $validated['filiere_ids']);
        }
        $ue->load(['filiere', 'filieres:id,code,intitule,niveau', 'ecs']);
        return $this->successResponse(new UeResource($ue), 'UE mise à jour.');
    }

    public function destroy(Request $request, Ue $ue): JsonResponse
    {
        $this->authorizeEtablissement($ue, $request, 'filiere');
        $this->refuserSiAnneeClose((int) $ue->annee_id, $request);
        $this->preventDeleteWithDependencies($ue, ['ecs' => 'EC']);

        $ue->delete();
        return $this->successResponse(null, 'UE supprimée.');
    }

}
