<?php

namespace App\Http\Controllers;

use App\Models\Presence;
use App\Models\Member;
use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class HeatmapController extends Controller
{
    /**
     * Affiche la heatmap de présence
     */
    public function index(Request $request)
    {
        $userGroup = Auth::user()->group;
        
        // Période par défaut: 30 derniers jours
        $startDate = $request->get('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));
        
        // Récupérer les données de présence
        $heatmapData = $this->generateHeatmapData($userGroup, $startDate, $endDate);
        
        // Statistiques globales
        $stats = $this->calculateStats($userGroup, $startDate, $endDate);
        
        return view('heatmap.index', compact('heatmapData', 'stats', 'startDate', 'endDate'));
    }

    /**
     * Génère les données pour la heatmap
     */
    private function generateHeatmapData(string $group, string $startDate, string $endDate): array
    {
        // Récupérer toutes les présences du groupe dans la période
        $presences = Presence::whereHas('member', function ($query) use ($group) {
                $query->where('group', $group);
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy(function ($presence) {
                return Carbon::parse($presence->date)->format('Y-m-d');
            })
            ->map(function ($dayPresences) {
                // Grouper par heure
                $hourlyData = [];
                foreach ($dayPresences as $presence) {
                    if ($presence->time) {
                        $hour = Carbon::parse($presence->time)->format('H');
                        if (!isset($hourlyData[$hour])) {
                            $hourlyData[$hour] = 0;
                        }
                        $hourlyData[$hour]++;
                    }
                }
                return [
                    'total' => $dayPresences->count(),
                    'hourly' => $hourlyData
                ];
            })
            ->toArray();

        // Générer la structure complète pour la période
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $heatmapData = [];

        while ($start <= $end) {
            $dateKey = $start->format('Y-m-d');
            $dayData = $presences[$dateKey] ?? ['total' => 0, 'hourly' => []];
            
            $heatmapData[] = [
                'date' => $dateKey,
                'dayOfWeek' => $start->dayOfWeek,
                'dayName' => $start->translatedFormat('D'),
                'week' => $start->weekOfYear,
                'total' => $dayData['total'],
                'hourly' => $dayData['hourly'],
                'intensity' => $this->calculateIntensity($dayData['total'])
            ];
            
            $start->addDay();
        }

        return $heatmapData;
    }

    /**
     * Calcule l'intensité de couleur (0-4)
     */
    private function calculateIntensity(int $count): int
    {
        if ($count === 0) return 0;
        if ($count <= 2) return 1;
        if ($count <= 5) return 2;
        if ($count <= 10) return 3;
        return 4;
    }

    /**
     * Calcule les statistiques globales
     */
    private function calculateStats(string $group, string $startDate, string $endDate): array
    {
        $totalMembers = Member::where('group', $group)->count();
        
        $totalPresences = Presence::whereHas('member', function ($query) use ($group) {
                $query->where('group', $group);
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->count();

        $totalEvents = QrCode::where('group', $group)
            ->whereBetween('event_date', [$startDate, $endDate])
            ->distinct('event_date')
            ->count('event_date');

        // Jour avec le plus de présences
        $bestDay = Presence::whereHas('member', function ($query) use ($group) {
                $query->where('group', $group);
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('date, COUNT(*) as count')
            ->groupBy('date')
            ->orderByDesc('count')
            ->first();

            // Heure la plus active
        $driver = \DB::connection()->getDriverName();
        $hourExtraction = $driver === 'sqlite' ? "strftime('%H', time)" : "EXTRACT(HOUR FROM time)";
        
        // Si c'est MySQL, c'est HOUR(time), mais EXTRACT(HOUR FROM time) est standard SQL
        // Pour Postgres: EXTRACT(HOUR FROM time)
        
        $bestHour = Presence::whereHas('member', function ($query) use ($group) {
                $query->where('group', $group);
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotNull('time')
            ->selectRaw("$hourExtraction as hour, COUNT(*) as count")
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();

        return [
            'total_members' => $totalMembers,
            'total_presences' => $totalPresences,
            'total_events' => $totalEvents,
            'avg_per_event' => $totalEvents > 0 ? round($totalPresences / $totalEvents, 1) : 0,
            'best_day' => $bestDay ? Carbon::parse($bestDay->date)->translatedFormat('l d M') : 'N/A',
            'best_day_count' => $bestDay->count ?? 0,
            'best_hour' => $bestHour ? $bestHour->hour . 'h' : 'N/A',
            'best_hour_count' => $bestHour->count ?? 0,
        ];
    }

    /**
     * API pour récupérer les données JSON (pour mise à jour dynamique)
     */
    public function getData(Request $request)
    {
        $userGroup = Auth::user()->group;
        $startDate = $request->get('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));
        
        $heatmapData = $this->generateHeatmapData($userGroup, $startDate, $endDate);
        $stats = $this->calculateStats($userGroup, $startDate, $endDate);
        
        return response()->json([
            'heatmapData' => $heatmapData,
            'stats' => $stats
        ]);
    }
}
