<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use App\Models\Presence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PresenceHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Presence::with(['etudiant.filiere', 'evenement.ec']);

        if ($search = $request->search) {
            $query->whereHas('etudiant', function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('date_debut')) {
            $query->whereDate('heure_scan', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->whereDate('heure_scan', '<=', $request->date_fin);
        }

        if ($request->filled('filiere_id')) {
            $query->whereHas('etudiant', fn($q) => $q->where('filiere_id', $request->filiere_id));
        }

        $perPage = min((int) $request->per_page, 100);
        $presences = $query->latest('heure_scan')->paginate($perPage ?: 15);

        return $this->paginatedResponse(
            $presences->through(fn($p) => [
                'id'         => $p->id,
                'etudiant'   => [
                    'id'        => $p->etudiant->id,
                    'nom'       => $p->etudiant->nom,
                    'prenom'    => $p->etudiant->prenom,
                    'matricule' => $p->etudiant->matricule,
                    'filiere'   => $p->etudiant->filiere?->code,
                ],
                'evenement'  => [
                    'id'    => $p->evenement->id,
                    'cours' => $p->evenement->ec?->intitule ?? 'N/A',
                    'date'  => $p->evenement->date?->format('Y-m-d'),
                ],
                'heure_scan' => $p->heure_scan->format('Y-m-d H:i:s'),
                'statut'     => $p->statut,
                'ip_address' => $p->ip_address,
            ])
        );
    }

    public function stats(Request $request): JsonResponse
    {
        $totalEtudiants = Etudiant::count();
        $totalPresences = Presence::count();
        $totalEvenements = DB::table('evenements')->count();

        $presencesParJour = Presence::select(
            DB::raw("DATE(heure_scan) as date"),
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) as valides"),
            DB::raw("SUM(CASE WHEN statut = 'suspect' THEN 1 ELSE 0 END) as suspectes")
        )
            ->where('heure_scan', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $statsParFiliere = Etudiant::select('filieres.code', 'filieres.intitule', DB::raw('COUNT(presences.id) as total_presences'))
            ->join('filieres', 'etudiants.filiere_id', '=', 'filieres.id')
            ->leftJoin('presences', 'etudiants.id', '=', 'presences.etudiant_id')
            ->groupBy('filieres.id', 'filieres.code', 'filieres.intitule')
            ->get();

        $tauxGlobal = $totalEvenements > 0 && $totalEtudiants > 0
            ? round(($totalPresences / ($totalEvenements * $totalEtudiants)) * 100, 1)
            : 0;

        return $this->successResponse([
            'total_etudiants'    => $totalEtudiants,
            'total_presences'    => $totalPresences,
            'total_evenements'   => $totalEvenements,
            'taux_global'        => $tauxGlobal,
            'presences_par_jour' => $presencesParJour,
            'stats_par_filiere'  => $statsParFiliere,
        ]);
    }

    public function studentStats(Etudiant $student): JsonResponse
    {
        $student->load(['filiere', 'presences.evenement.ec']);

        $totalEvenements = DB::table('evenements')
            ->where('filiere_id', $student->filiere_id)
            ->where('annee_id', $student->annee_id)
            ->count();

        $presencesCount = $student->presences()->count();
        $absencesCount = max(0, $totalEvenements - $presencesCount);
        $taux = $totalEvenements > 0 ? round(($presencesCount / $totalEvenements) * 100, 1) : 0;

        $statsParCours = $student->presences()
            ->select('evenement_id', DB::raw('COUNT(*) as total'))
            ->groupBy('evenement_id')
            ->get()
            ->map(fn($p) => [
                'cours'   => $p->evenement->ec?->intitule ?? 'N/A',
                'code'    => $p->evenement->ec?->code ?? 'N/A',
                'total'   => $p->total,
            ]);

        $recentHistory = $student->presences()
            ->with('evenement.ec')
            ->latest('heure_scan')
            ->take(10)
            ->get()
            ->map(fn($p) => [
                'date'   => $p->heure_scan->format('Y-m-d H:i'),
                'cours'  => $p->evenement->ec?->intitule ?? 'N/A',
                'statut' => $p->statut,
            ]);

        return $this->successResponse([
            'etudiant'         => [
                'id'        => $student->id,
                'nom'       => $student->nom,
                'prenom'    => $student->prenom,
                'matricule' => $student->matricule,
                'filiere'   => $student->filiere?->code,
            ],
            'total_evenements' => $totalEvenements,
            'total_presences'  => $presencesCount,
            'total_absences'   => $absencesCount,
            'taux_presence'    => $taux,
            'stats_par_cours'  => $statsParCours,
            'recent_history'   => $recentHistory,
        ]);
    }
}
