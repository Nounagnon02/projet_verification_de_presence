<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ec;
use App\Models\Etudiant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EnrollmentController extends Controller
{
    /**
     * Liste des ECs auxquels un étudiant est inscrit.
     * GET /api/admin/students/{student}/ecs
     */
    public function index(Etudiant $student): JsonResponse
    {
        $ecs = $student->ecs()->with(['ue'])->get();

        return $this->successResponse($ecs, 'Liste des ECs de l\'étudiant.');
    }

    /**
     * Liste des ECs disponibles pour la filière/année de l'étudiant.
     * GET /api/admin/students/{student}/ecs-available
     */
    public function available(Etudiant $student): JsonResponse
    {
        $allEcs = Ec::forFiliereAndYear($student->filiere_id, $student->annee_id);
        $enrolledIds = $student->ecs()->pluck('ecs.id')->toArray();

        $available = $allEcs->filter(fn ($ec) => !in_array($ec->id, $enrolledIds))->values();

        return $this->successResponse($available, 'ECs disponibles pour inscription.');
    }

    /**
     * Inscrire un étudiant à un ou plusieurs ECs.
     * POST /api/admin/students/{student}/ecs
     */
    public function store(Request $request, Etudiant $student): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ec_ids'   => 'required|array|min:1',
            'ec_ids.*' => 'required|integer|exists:ecs,id',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $attached = 0;
        foreach ($request->ec_ids as $ecId) {
            // Vérifier que l'EC appartient bien à la filière/année de l'étudiant
            $ec = Ec::where('id', $ecId)
                ->whereHas('ue', function ($q) use ($student) {
                    $q->where('filiere_id', $student->filiere_id)
                      ->where('annee_id', $student->annee_id);
                })->first();

            if (!$ec) continue;

            $student->ecs()->syncWithoutDetaching([
                $ecId => ['annee_id' => $student->annee_id],
            ]);
            $attached++;
        }

        return $this->successResponse([
            'attached' => $attached,
            'total'    => count($request->ec_ids),
        ], "{$attached} EC(s) ajouté(s).");
    }

    /**
     * Désinscrire un étudiant d'un EC.
     * DELETE /api/admin/students/{student}/ecs/{ec}
     */
    public function destroy(Etudiant $student, Ec $ec): JsonResponse
    {
        $student->ecs()->detach($ec->id);

        return $this->successResponse(null, 'Étudiant désinscrit de cet EC.');
    }

    /**
     * Ré-inscrire un étudiant à tous les ECs de sa filière/année (reset).
     * POST /api/admin/students/{student}/ecs/reset
     */
    public function reset(Etudiant $student): JsonResponse
    {
        // Supprimer les inscriptions existantes
        $student->ecs()->detach();

        // Ré-inscrire à tous les ECs de la filière/année
        $student->autoEnroll();

        $count = $student->ecs()->count();

        return $this->successResponse([
            'enrolled' => $count,
        ], "Ré-inscription terminée : {$count} EC(s).");
    }
}
