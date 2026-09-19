<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgrammeResource;
use App\Models\Programme;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgrammeController extends Controller
{
    use ScopedByEtablissement;

    /**
     * Programmes de l'établissement (IM, MIAGE…), chacun avec son nombre de
     * filières — une par niveau ouvert.
     *
     * GET /api/admin/programmes
     */
    public function index(Request $request): JsonResponse
    {
        $query = Programme::withCount('filieres')->orderBy('code');

        $this->scopeQuery($query, $request);

        return $this->successResponse(ProgrammeResource::collection($query->get(['id', 'etablissement_id', 'code', 'intitule'])));
    }

    /**
     * Renomme un programme. Seul l'intitulé change : le code sert de préfixe
     * aux codes des filières (IM → IM-L1, IM-L2…), le modifier les
     * désaccorderait toutes.
     *
     * renommer_filieres : renomme aussi les filières dont l'intitulé suit
     * encore le motif dérivé « {ancien intitulé} ({niveau}) ». Une filière
     * dont l'intitulé a été personnalisé n'est pas touchée.
     *
     * PUT /api/admin/programmes/{programme}
     */
    public function update(Request $request, Programme $programme): JsonResponse
    {
        $this->authorizeEtablissement($programme, $request);

        $valeurs = $request->validate([
            'intitule'          => ['required', 'string', 'max:255'],
            'renommer_filieres' => ['sometimes', 'boolean'],
        ]);

        $ancien = $programme->intitule;
        $nouveau = trim($valeurs['intitule']);
        $renommees = [];

        DB::transaction(function () use ($programme, $ancien, $nouveau, $request, &$renommees) {
            $programme->update(['intitule' => $nouveau]);

            if (!$request->boolean('renommer_filieres') || $ancien === $nouveau) {
                return;
            }

            foreach ($programme->filieres()->orderBy('code')->get() as $filiere) {
                if ($filiere->intitule === "{$ancien} ({$filiere->niveau})") {
                    $filiere->update(['intitule' => "{$nouveau} ({$filiere->niveau})"]);
                    $renommees[] = $filiere->code;
                }
            }
        });

        $message = "Programme {$programme->code} renommé.";
        if ($renommees !== []) {
            $message .= ' Filières renommées : ' . implode(', ', $renommees) . '.';
        }

        return $this->successResponse(
            ['id' => $programme->id, 'code' => $programme->code, 'intitule' => $nouveau, 'filieres_renommees' => $renommees],
            $message
        );
    }
}
