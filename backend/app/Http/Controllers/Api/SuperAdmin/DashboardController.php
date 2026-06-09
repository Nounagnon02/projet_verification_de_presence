<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Presence;
use App\Models\Evenement;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Statistiques globales UAC pour le Super Admin.
     * GET /api/super-admin/dashboard
     */
    public function index(): JsonResponse
    {
        $totalFacultes = Etablissement::count();
        $totalEtudiants = Etudiant::count();
        $totalPresences = Presence::count();
        $coursAujourdhui = Evenement::whereDate('date', today())->count();

        $facultes = Etablissement::withCount([
            'filieres',
            'users' => fn ($q) => $q->where('role', 'faculte_admin'),
        ])->get()->map(fn ($e) => [
            'id'             => $e->id,
            'code'           => $e->code,
            'nom'            => $e->nom,
            'filieres_count' => $e->filieres_count,
            'admins_count'   => $e->users_count,
            'actif'          => $e->actif,
        ]);

        return $this->successResponse([
            'total_facultes'       => $totalFacultes,
            'total_etudiants'      => $totalEtudiants,
            'total_presences'      => $totalPresences,
            'cours_aujourdhui'     => $coursAujourdhui,
            'facultes'             => $facultes,
        ]);
    }
}
