<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Anomaly;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Presence;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Statistiques clés pour le tableau de bord (US05).
     * Optimisé avec un minimum de requêtes SQL.
     *
     * GET /api/admin/dashboard
     */
    public function index(): JsonResponse
    {
        $totalEtudiants = Etudiant::count();
        $coursDuJour    = Evenement::whereDate('date', today())->count();

        $presencesDuJour = Presence::whereDate('heure_scan', today())
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) as valides")
            ->selectRaw("SUM(CASE WHEN statut = 'suspect' THEN 1 ELSE 0 END) as suspectes")
            ->first();

        $evenementsPasses = Evenement::where('date', '<', now())
            ->where('statut', 'termine')
            ->count();

        $presencesTotales = Presence::count();
        $tauxPresenceGlobal = ($evenementsPasses > 0 && $totalEtudiants > 0)
            ? round(($presencesTotales / ($evenementsPasses * $totalEtudiants)) * 100, 1)
            : 0;

        $fraudesSuspectees = Anomaly::where('resolved', false)->count();

        $dernieresAnomalies = Anomaly::with('member')
            ->where('resolved', false)
            ->latest()
            ->take(5)
            ->get();

        $heatmapAujourdhui = Presence::whereDate('heure_scan', today())
            ->select(DB::raw("EXTRACT(HOUR FROM heure_scan) as heure"), DB::raw('COUNT(*) as total'))
            ->groupBy('heure')
            ->orderBy('heure')
            ->pluck('total', 'heure');

        return $this->successResponse([
            'total_etudiants'       => $totalEtudiants,
            'cours_du_jour'         => $coursDuJour,
            'presences_aujourd_hui' => (int) $presencesDuJour->total,
            'presences_valides'     => (int) $presencesDuJour->valides,
            'presences_suspectes'   => (int) $presencesDuJour->suspectes,
            'taux_presence_global'  => $tauxPresenceGlobal,
            'fraudes_suspectees'    => $fraudesSuspectees,
            'dernieres_anomalies'   => $dernieresAnomalies->map(fn ($a) => [
                'id'          => $a->id,
                'type'        => $a->type,
                'severite'    => $a->severity,
                'description' => $a->description,
                'creee_le'    => $a->created_at,
            ]),
            'heatmap' => $heatmapAujourdhui,
        ]);
    }

    /**
     * Données pour la Heatmap hebdomadaire (CDC 4.3).
     * GET /api/admin/dashboard/heatmap
     */
    public function heatmap(): JsonResponse
    {
        $data = Presence::select(
            DB::raw('EXTRACT(HOUR FROM heure_scan) as hour'),
            DB::raw('EXTRACT(DOW FROM heure_scan) as day'),
            DB::raw('COUNT(*) as count')
        )
        ->where('heure_scan', '>=', now()->subDays(30))
        ->groupBy('hour', 'day')
        ->orderBy('day')
        ->orderBy('hour')
        ->get();

        return $this->successResponse($data);
    }
}
