<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Presence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Statistiques globales pour le dashboard (US05).
     */
    public function index()
    {
        $totalStudents = Etudiant::count();
        $presencesToday = Presence::whereDate('heure_scan', today())->count();
        
        // Taux de présence moyen (simulé)
        $avgAttendance = Presence::count() > 0 ? (Presence::count() / (Evenement::where('statut', 'termine')->count() ?: 1)) : 0;

        // Alertes de fraude (US06)
        $fraudAlerts = Presence::where('statut', 'suspect')->latest()->take(5)->get();

        return response()->json([
            'stats' => [
                'total_students' => $totalStudents,
                'presences_today' => $presencesToday,
                'avg_attendance' => round($avgAttendance, 2),
            ],
            'recent_alerts' => $fraudAlerts
        ]);
    }

    /**
     * Données pour la Heatmap (CDC 4.3).
     */
    public function heatmap()
    {
        $data = Presence::select(
            DB::raw('EXTRACT(HOUR FROM heure_scan) as hour'),
            DB::raw('EXTRACT(DOW FROM heure_scan) as day'),
            DB::raw('count(*) as count')
        )
        ->groupBy('hour', 'day')
        ->get();

        return response()->json($data);
    }
}
