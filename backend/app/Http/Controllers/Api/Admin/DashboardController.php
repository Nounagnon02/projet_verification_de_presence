<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Anomaly;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Presence;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ScopedByEtablissement;

    /**
     * Helper : applique le filtre etablissement aux requêtes Evenement.
     */
    private function scopeEvenement($query, ?int $etablissementId)
    {
        if ($etablissementId) {
            $query->whereHas('filiere', fn($q) => $q->where('etablissement_id', $etablissementId));
        }
        return $query;
    }

    /**
     * Helper : applique le filtre etablissement aux requêtes Presence.
     */
    private function scopePresence($query, ?int $etablissementId)
    {
        if ($etablissementId) {
            $query->whereHas('etudiant.filiere', fn($q) => $q->where('etablissement_id', $etablissementId));
        }
        return $query;
    }

    /**
     * Helper : applique le filtre etablissement aux requêtes Etudiant.
     */
    private function scopeEtudiant($query, ?int $etablissementId)
    {
        if ($etablissementId) {
            $query->whereHas('filiere', fn($q) => $q->where('etablissement_id', $etablissementId));
        }
        return $query;
    }

    /**
     * Statistiques clés pour le tableau de bord (US05).
     * Optimisé avec un minimum de requêtes SQL.
     *
     * GET /api/admin/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);

        $totalEtudiants = $this->scopeEtudiant(Etudiant::query(), $etablissementId)->count();
        $coursDuJour    = $this->scopeEvenement(Evenement::whereDate('date', today()), $etablissementId)->count();

        $presencesDuJour = $this->scopePresence(Presence::whereDate('heure_scan', today()), $etablissementId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) as valides")
            ->selectRaw("SUM(CASE WHEN statut = 'suspect' THEN 1 ELSE 0 END) as suspectes")
            ->first();

        $evenementsPasses = $this->scopeEvenement(Evenement::where('date', '<', now())
            ->where('statut', 'termine'), $etablissementId)->count();

        $presencesTotales = $this->scopePresence(Presence::query(), $etablissementId)->count();
        $tauxPresenceGlobal = ($evenementsPasses > 0 && $totalEtudiants > 0)
            ? round(($presencesTotales / ($evenementsPasses * $totalEtudiants)) * 100, 1)
            : 0;

        $fraudesSuspectees = Anomaly::where('resolved', false)->count();

        $dernieresAnomalies = Anomaly::with('member')
            ->where('resolved', false)
            ->latest()
            ->take(5)
            ->get();

        $heatmapAujourdhui = $this->scopePresence(Presence::whereDate('heure_scan', today()), $etablissementId)
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
    public function heatmap(Request $request): JsonResponse
    {
        $data = $this->scopePresence(Presence::select(
            DB::raw('EXTRACT(HOUR FROM heure_scan) as hour'),
            DB::raw('EXTRACT(DOW FROM heure_scan) as day'),
            DB::raw('COUNT(*) as count')
        )
        ->where('heure_scan', '>=', now()->subDays(30)), $this->getEtablissementId($request))
        ->groupBy('hour', 'day')
        ->orderBy('day')
        ->orderBy('hour')
        ->get();

        return $this->successResponse($data);
    }

    /**
     * Tendance des présences sur 30 jours pour les graphiques.
     * GET /api/admin/dashboard/attendance-trend
     */
    public function attendanceTrend(Request $request): JsonResponse
    {
        $trend = $this->scopePresence(Presence::select(
            DB::raw('DATE(heure_scan) as date'),
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) as valides"),
            DB::raw("SUM(CASE WHEN statut = 'suspect' THEN 1 ELSE 0 END) as suspectes")
        )
            ->where('heure_scan', '>=', now()->subDays(30)), $this->getEtablissementId($request))
            ->groupBy(DB::raw('DATE(heure_scan)'))
            ->orderBy('date')
            ->get();

        return $this->successResponse($trend);
    }

    /**
     * Top 10 des étudiants les plus absents.
     * GET /api/admin/dashboard/top-absences
     */
    public function topAbsences(Request $request): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);

        $totalEvenements = $this->scopeEvenement(Evenement::where('date', '<', now()), $etablissementId)->count();

        $topAbsences = $this->scopeEtudiant(Etudiant::with('filiere')
            ->select('etudiants.id', 'etudiants.nom', 'etudiants.prenom', 'etudiants.matricule', 'filieres.code as filiere_code')
            ->join('filieres', 'etudiants.filiere_id', '=', 'filieres.id'), $etablissementId)
            ->selectRaw("COALESCE((SELECT COUNT(*) FROM presences WHERE presences.etudiant_id = etudiants.id), 0) as total_presences")
            ->selectRaw("? - COALESCE((SELECT COUNT(*) FROM presences WHERE presences.etudiant_id = etudiants.id), 0) as absences", [$totalEvenements])
            ->orderBy('absences', 'desc')
            ->take(10)
            ->get();

        return $this->successResponse($topAbsences);
    }

    /**
     * Événements du jour pour la timeline.
     * GET /api/admin/dashboard/today-events
     */
    public function todayEvents(Request $request): JsonResponse
    {
        $events = $this->scopeEvenement(Evenement::with(['ec', 'filiere', 'presences'])
            ->whereDate('date', today())
            ->orderBy('heure_debut'), $this->getEtablissementId($request))
            ->get()
            ->map(fn($e) => [
                'id'              => $e->id,
                'cours'           => $e->ec?->intitule ?? 'N/A',
                'filiere'         => $e->filiere?->code ?? 'N/A',
                'heure_debut'     => $e->heure_debut,
                'heure_fin'       => $e->heure_fin,
                'salle'           => $e->salle,
                'statut'          => $e->statut,
                'presences_count' => $e->presences->count(),
            ]);

        return $this->successResponse($events);
    }
}
