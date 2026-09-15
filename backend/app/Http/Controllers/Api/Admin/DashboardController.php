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
    public function index(Request $request, \App\Services\AttendanceRateService $attendance): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);

        $totalEtudiants = $this->scopeEtudiant(Etudiant::query(), $etablissementId)->count();
        // where() et non whereDate() : `date` est une colonne DATE, et
        // whereDate() la passerait dans DATE(...) en désactivant l'index.
        $coursDuJour    = $this->scopeEvenement(Evenement::where('date', today()), $etablissementId)->count();

        $presencesDuJour = $this->scopePresence(Presence::whereBetween('heure_scan', [today()->startOfDay(), today()->endOfDay()]), $etablissementId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) as valides")
            ->selectRaw("SUM(CASE WHEN statut = 'suspect' THEN 1 ELSE 0 END) as suspectes")
            ->first();

        $evenementsPasses = $this->scopeEvenement(Evenement::where('date', '<', now())
            ->where('statut', 'termine'), $etablissementId)->count();

        // Taux calculé sur les présences réellement attendues (étudiants
        // inscrits à l'EC de chaque événement passé), et non sur « tous les
        // étudiants × tous les événements » qui écrasait le taux.
        $filtreEvenementsPasses = function ($q) use ($etablissementId) {
            $q->where('e.date', '<', now())->where('e.statut', 'termine');
            if ($etablissementId) {
                $q->join('filieres as f', 'f.id', '=', 'e.filiere_id')
                  ->where('f.etablissement_id', $etablissementId);
            }
        };
        $tauxPresenceGlobal = $attendance->rate($filtreEvenementsPasses);

        // Anomalies cloisonnées par établissement (via l'étudiant → filière).
        $scopeAnomalies = function ($query) use ($etablissementId) {
            if ($etablissementId) {
                $query->whereHas('etudiant.filiere', fn ($q) => $q->where('etablissement_id', $etablissementId));
            }
            return $query;
        };

        // Scans à arbitrer : les présences suspectes de la file de validation,
        // seul lieu de décision. L'ancien compteur « fraudes suspectées »
        // comptait les anomalies ouvertes, pour la plupart des scans déjà
        // refusés (rien à trancher), et qui ne se fermaient jamais.
        $scansAArbitrer = $this->scopePresence(Presence::where('statut', 'suspect'), $etablissementId)->count();

        // Scans refusés du jour, pour information : aucune présence créée.
        $scansRefusesDuJour = $scopeAnomalies(
            Anomaly::whereIn('type', Anomaly::TYPES_SCAN_REFUSE)->where('created_at', '>=', today())
        )->count();

        $heatmapAujourdhui = $this->scopePresence(Presence::whereBetween('heure_scan', [today()->startOfDay(), today()->endOfDay()]), $etablissementId)
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
            'scans_a_arbitrer'      => $scansAArbitrer,
            'scans_refuses_du_jour' => $scansRefusesDuJour,
            'heatmap' => $heatmapAujourdhui,
        ]);
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

        // Le decompte porte sur les seances passees de la filiere de l'etudiant,
        // et les presences comptees sont celles rattachees a ces memes seances.
        //
        // La version precedente comparait deux perimetres differents : un total
        // d'evenements passes de tout l'etablissement d'un cote, et le nombre
        // total de presences de l'etudiant de l'autre, toutes seances confondues.
        // Deux consequences : un etudiant de la filiere A etait compte absent aux
        // seances de la filiere B, et « absences » pouvait devenir NEGATIF des
        // qu'un etudiant avait plus de presences que le total retenu au
        // denominateur. Ici les deux membres partagent le meme perimetre, donc
        // les presences sont par construction un sous-ensemble des seances et la
        // difference reste positive ou nulle.
        // Séances où l'étudiant était attendu : celles des EC auxquels il est
        // inscrit pour leur année ; à défaut de toute inscription, celles des cours
        // que suit sa filière (cours communs compris) — la règle du scan,
        // Etudiant::scopeAttendusA. On comparait la filière de la SÉANCE à la
        // sienne : un cours commun porté par une autre filière ne comptait pas, et
        // un cours de sa filière auquel il n'est pas inscrit comptait.
        $attenduA = fn (string $ev) => "(EXISTS (SELECT 1 FROM etudiant_ec ee
                                            WHERE ee.etudiant_id = etudiants.id
                                              AND ee.ec_id = {$ev}.ec_id
                                              AND ee.annee_id = {$ev}.annee_id)
                                      OR (NOT EXISTS (SELECT 1 FROM etudiant_ec ee0 WHERE ee0.etudiant_id = etudiants.id)
                                          AND {$ev}.ec_id IN (SELECT c.id FROM ecs c
                                                                JOIN ue_filiere uf ON uf.ue_id = c.ue_id
                                                               WHERE uf.filiere_id = etudiants.filiere_id)))";
        $attenduSeance = $attenduA('ev');
        $attenduPresence = $attenduA('ev2');

        $seancesPassees = "(SELECT COUNT(*) FROM evenements ev
                              WHERE {$attenduSeance}
                                AND ev.deleted_at IS NULL
                                AND ev.date < now())";

        $presencesRetenues = "(SELECT COUNT(*) FROM presences p
                                 JOIN evenements ev2 ON ev2.id = p.evenement_id
                                WHERE p.etudiant_id = etudiants.id
                                  AND {$attenduPresence}
                                  AND ev2.deleted_at IS NULL
                                  AND ev2.date < now())";

        $topAbsences = $this->scopeEtudiant(Etudiant::with('filiere')
            ->select('etudiants.id', 'etudiants.nom', 'etudiants.prenom', 'etudiants.matricule', 'filieres.code as filiere_code')
            ->join('filieres', 'etudiants.filiere_id', '=', 'filieres.id'), $etablissementId)
            ->selectRaw("COALESCE({$presencesRetenues}, 0) as total_presences")
            ->selectRaw("COALESCE({$seancesPassees}, 0) - COALESCE({$presencesRetenues}, 0) as absences")
            ->orderByRaw("COALESCE({$seancesPassees}, 0) - COALESCE({$presencesRetenues}, 0) DESC")
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
        // withCount plutôt que with('presences') : on ne charge pas toutes les
        // lignes de présence pour n'en compter que le nombre.
        // where('date', ...) et non whereDate() : whereDate() enveloppe la
        // colonne dans DATE(), ce qui empêche Postgres d'utiliser l'index.
        $events = $this->scopeEvenement(Evenement::with(['ec', 'filiere'])
            ->withCount('presences')
            ->where('date', today())
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
                'presences_count' => $e->presences_count,
            ]);

        return $this->successResponse($events);
    }
}
