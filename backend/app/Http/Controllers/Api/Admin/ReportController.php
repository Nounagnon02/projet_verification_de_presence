<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\Ue;
use App\Services\SemesterService;
use App\Traits\ScopedByEtablissement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use ScopedByEtablissement;
    /**
     * Export des présences au format PDF (CDC 15.2 & 16).
     *
     * GET /api/admin/reports/presence/{evenementId}/pdf
     */
    public function exportPdf(Request $request, int $evenementId): mixed
    {
        $evenement = Evenement::with(['ec', 'presences.etudiant'])->findOrFail($evenementId);

        $data = [
            'evenement' => $evenement,
            'date'      => now()->format('d/m/Y H:i'),
            'title'     => 'Rapport de Présence - ' . ($evenement->ec->intitule ?? 'N/A'),
        ];

        $pdf = Pdf::loadView('reports.presence', $data);

        return $pdf->download("presence_{$evenementId}_" . now()->format('Ymd_His') . ".pdf");
    }

    /**
     * Rapport de présence par département/filière.
     * GET /api/admin/reports/department/{filiere}
     */
    public function departmentReport(Filiere $filiere): JsonResponse
    {
        $totalEtudiants = Etudiant::where('filiere_id', $filiere->id)->count();
        $totalEvenements = Evenement::where('filiere_id', $filiere->id)->where('date', '<', now())->count();

        $presences = Presence::whereHas('etudiant', fn($q) => $q->where('filiere_id', $filiere->id))
            ->whereHas('evenement', fn($q) => $q->where('filiere_id', $filiere->id))
            ->count();

        $taux = $totalEvenements > 0 && $totalEtudiants > 0
            ? round(($presences / ($totalEvenements * $totalEtudiants)) * 100, 1)
            : 0;

        $presencesParCours = Evenement::where('filiere_id', $filiere->id)
            ->with('ec')
            ->withCount('presences')
            ->orderBy('date', 'desc')
            ->take(20)
            ->get()
            ->map(fn($e) => [
                'cours'          => $e->ec?->intitule ?? 'N/A',
                'date'           => $e->date?->format('Y-m-d'),
                'presences_count' => $e->presences_count,
            ]);

        return $this->successResponse([
            'filiere'            => ['id' => $filiere->id, 'code' => $filiere->code, 'intitule' => $filiere->intitule],
            'total_etudiants'    => $totalEtudiants,
            'total_evenements'   => $totalEvenements,
            'total_presences'    => $presences,
            'taux_presence'      => $taux,
            'presences_par_cours' => $presencesParCours,
        ]);
    }

    /**
     * Rapport de présence par semestre/année académique.
     * GET /api/admin/reports/semester/{anneeAcademique}?filiere_id=X&semestre=N
     *
     * Query params optionnels :
     *   - filiere_id : filtrer par filière
     *   - semestre   : filtrer par numéro de semestre (1-10)
     */
    public function semesterReport(Request $request, AnneeAcademique $anneeAcademique): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);

        $query = Ue::selectRaw('
                ues.semestre,
                COUNT(DISTINCT presences.id) as total_presences,
                COUNT(DISTINCT evenements.id) as total_evenements,
                COUNT(DISTINCT etudiant_ec.etudiant_id) as total_etudiants
            ')
            ->join('ecs', 'ecs.ue_id', '=', 'ues.id')
            ->join('evenements', 'evenements.ec_id', '=', 'ecs.id')
            ->leftJoin('presences', 'presences.evenement_id', '=', 'evenements.id')
            ->leftJoin('etudiant_ec', function ($join) use ($anneeAcademique) {
                $join->on('etudiant_ec.ec_id', '=', 'ecs.id')
                    ->on('etudiant_ec.annee_id', '=', DB::raw($anneeAcademique->id));
            })
            ->where('ues.annee_id', $anneeAcademique->id)
            ->groupBy('ues.semestre')
            ->orderBy('ues.semestre');

        // Scope par établissement via la filière
        if ($etablissementId) {
            $query->join('filieres', 'ues.filiere_id', '=', 'filieres.id')
                  ->where('filieres.etablissement_id', $etablissementId);
        }

        // Filtre optionnel par filière
        if ($request->filled('filiere_id')) {
            $query->where('ues.filiere_id', $request->integer('filiere_id'));
        }

        // Filtre optionnel par semestre
        if ($request->filled('semestre')) {
            $query->where('ues.semestre', $request->integer('semestre'));
        }

        $statsParSemestre = $query->get()->map(function ($row) {
            $totalAttendus = ($row->total_evenements ?? 0) * ($row->total_etudiants ?? 1);
            return [
                'semestre'         => (int) $row->semestre,
                'label'            => 'S' . $row->semestre,
                'taux'             => $totalAttendus > 0 ? round(($row->total_presences / $totalAttendus) * 100, 1) : 0,
                'total_presences'  => (int) $row->total_presences,
                'total_evenements' => (int) $row->total_evenements,
                'total_etudiants'  => (int) $row->total_etudiants,
            ];
        });

        // Totaux globaux
        $totalPresences = $statsParSemestre->sum('total_presences');
        $totalEvenements = $statsParSemestre->sum('total_evenements');
        $totalEtudiants = Etudiant::where('annee_id', $anneeAcademique->id)->count();

        $filieres = Filiere::select('filieres.id', 'filieres.code', 'filieres.intitule', 'filieres.niveau',
                DB::raw('COUNT(DISTINCT presences.id) as total_presences'),
                DB::raw('COUNT(DISTINCT evenements.id) as total_evenements'))
            ->join('ues', 'ues.filiere_id', '=', 'filieres.id')
            ->join('ecs', 'ecs.ue_id', '=', 'ues.id')
            ->join('evenements', 'evenements.ec_id', '=', 'ecs.id')
            ->leftJoin('presences', 'presences.evenement_id', '=', 'evenements.id')
            ->where('ues.annee_id', $anneeAcademique->id)
            ->groupBy('filieres.id', 'filieres.code', 'filieres.intitule', 'filieres.niveau');

        // Scope par établissement
        if ($etablissementId) {
            $filieres->where('filieres.etablissement_id', $etablissementId);
        }

        $filieres = $filieres->get()
            ->map(function ($f) {
                $totalAttendus = ($f->total_evenements ?? 0) * Etudiant::where('filiere_id', $f->id)->where('annee_id', request()->route('anneeAcademique') ?: 0)->count();
                return [
                    'id'               => $f->id,
                    'code'             => $f->code,
                    'intitule'         => $f->intitule,
                    'niveau'           => $f->niveau,
                    'taux'             => $totalAttendus > 0 ? round(($f->total_presences / $totalAttendus) * 100, 1) : 0,
                    'total_presences'  => (int) $f->total_presences,
                ];
            });

        return $this->successResponse([
            'annee_academique'     => ['id' => $anneeAcademique->id, 'annee' => $anneeAcademique->libelle],
            'total_etudiants'      => $totalEtudiants,
            'total_evenements'     => $totalEvenements,
            'total_presences'      => $totalPresences,
            'taux_presence'        => $totalEvenements > 0 && $totalEtudiants > 0
                ? round(($totalPresences / ($totalEvenements * max($totalEtudiants, 1))) * 100, 1)
                : 0,
            'stats_par_semestre'   => $statsParSemestre,
            'stats_par_filiere'    => $filieres,
        ]);
    }

    /**
     * Comparaison des taux de présence entre deux semestres pour une filière.
     * GET /api/admin/reports/semester-comparison?filiere_id=X&annee_id=Y
     */
    public function semesterComparison(Request $request): JsonResponse
    {
        $anneeId = $request->integer('annee_id');
        $filiereId = $request->integer('filiere_id');

        if (!$anneeId || !$filiereId) {
            return $this->errorResponse('Les paramètres annee_id et filiere_id sont requis.', 422);
        }

        $filiere = Filiere::findOrFail($filiereId);
        $semesterService = app(SemesterService::class);
        $semestres = $semesterService->getSemestersForFiliere($filiere);

        $data = [];
        foreach ($semestres as $sem) {
            $stats = $semesterService->tauxParSemestre($anneeId, $filiereId)
                ->firstWhere('semestre', $sem);

            $data[] = [
                'semestre'        => $sem,
                'label'           => "S{$sem}",
                'taux'            => $stats['taux'] ?? 0,
                'total_presences' => $stats['total_presences'] ?? 0,
            ];
        }

        return $this->successResponse([
            'filiere'    => ['id' => $filiere->id, 'code' => $filiere->code, 'intitule' => $filiere->intitule, 'niveau' => $filiere->niveau],
            'semestres'  => $data,
        ]);
    }

    /**
     * Stats globales des filières pour les graphiques de comparaison.
     * GET /api/admin/reports/filiere-stats?annee_id=X
     */
    public function filiereStats(Request $request): JsonResponse
    {
        $anneeId = $request->integer('annee_id');

        $filiereQuery = Filiere::select('filieres.*')
            ->withCount(['etudiants' => function ($q) use ($anneeId) {
                $q->where('annee_id', $anneeId);
            }]);

        $this->scopeQuery($filiereQuery, $request);

        $filieres = $filiereQuery->get()
            ->map(function ($filiere) use ($anneeId) {
                $totalEvenements = Evenement::where('filiere_id', $filiere->id)
                    ->where('annee_id', $anneeId)
                    ->where('date', '<', now())
                    ->count();

                $totalPresences = Presence::whereHas('etudiant', fn($q) => $q->where('filiere_id', $filiere->id))
                    ->whereHas('evenement', fn($q) => $q->where('filiere_id', $filiere->id)->where('annee_id', $anneeId))
                    ->count();

                $totalAttendus = $totalEvenements * max($filiere->etudiants_count, 1);
                $taux = $totalAttendus > 0 ? round(($totalPresences / $totalAttendus) * 100, 1) : 0;

                return [
                    'id'               => $filiere->id,
                    'code'             => $filiere->code,
                    'intitule'         => $filiere->intitule,
                    'niveau'           => $filiere->niveau,
                    'etudiants_count'  => $filiere->etudiants_count ?? 0,
                    'taux'             => $taux,
                    'total_presences'  => $totalPresences,
                    'total_evenements' => $totalEvenements,
                ];
            })
            ->sortByDesc('taux')
            ->values();

        return $this->successResponse($filieres);
    }

    /**
     * Rapport filtré avec tous les paramètres : filière, année, semestre,
     * trimestre, UE, EC, plage de dates.
     *
     * GET /api/admin/reports/filtered
     *
     * Query params (tous optionnels) :
     *   - filiere_id
     *   - annee_id
     *   - semestre (1-10)
     *   - ue_id
     *   - ec_id
     *   - trimestre (1, 2, 3 — divisé sur l'année académique)
     *   - date_debut (YYYY-MM-DD)
     *   - date_fin   (YYYY-MM-DD)
     */
    public function filteredStats(Request $request): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);

        //1. Construire la requête de base
        $query = Presence::query()
            ->selectRaw('COUNT(DISTINCT presences.id) as total_presences')
            ->selectRaw('COUNT(DISTINCT evenements.id) as total_evenements')
            ->selectRaw("SUM(CASE WHEN presences.statut = 'valide' THEN 1 ELSE 0 END) as presences_valides")
            ->selectRaw("SUM(CASE WHEN presences.statut = 'suspect' THEN 1 ELSE 0 END) as presences_suspectes")
            ->join('evenements', 'presences.evenement_id', '=', 'evenements.id')
            ->join('ecs', 'evenements.ec_id', '=', 'ecs.id')
            ->join('ues', 'ecs.ue_id', '=', 'ues.id')
            ->join('etudiants', 'presences.etudiant_id', '=', 'etudiants.id');

        // Scope par établissement via filières des UEs
        if ($etablissementId) {
            $query->whereExists(function ($q) use ($etablissementId) {
                $q->selectRaw('1')
                  ->from('filieres')
                  ->whereColumn('filieres.id', 'ues.filiere_id')
                  ->where('filieres.etablissement_id', $etablissementId);
            });
        }

        //2. Appliquer les filtres
        if ($request->filled('filiere_id')) {
            $filiereId = $request->integer('filiere_id');
            $query->where('evenements.filiere_id', $filiereId)
                  ->where('ues.filiere_id', $filiereId);
        }

        if ($request->filled('annee_id')) {
            $anneeId = $request->integer('annee_id');
            $query->where('evenements.annee_id', $anneeId)
                  ->where('ues.annee_id', $anneeId);
        }

        if ($request->filled('semestre')) {
            $query->where('ues.semestre', $request->integer('semestre'));
        }

        if ($request->filled('ue_id')) {
            $query->where('ecs.ue_id', $request->integer('ue_id'));
        }

        if ($request->filled('ec_id')) {
            $query->where('evenements.ec_id', $request->integer('ec_id'));
        }

        if ($request->filled('date_debut')) {
            $query->whereDate('presences.heure_scan', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->whereDate('presences.heure_scan', '<=', $request->date_fin);
        }

        //3. Filtre trimestre (découpage de l'année académique)
        if ($request->filled('trimestre')) {
            $trimestre = $request->integer('trimestre');
            // L'année académique commence en septembre
            // T1: sept-oct-nov, T2: déc-janv-fév, T3: mars-avril-mai, T4: juin-juil-août
            if ($trimestre === 1) {
                $query->where(function ($q) {
                    $q->whereMonth('evenements.date', '>=', 9)
                      ->whereMonth('evenements.date', '<=', 11);
                });
            } elseif ($trimestre === 2) {
                $query->where(function ($q) {
                    $q->whereIn(DB::raw('EXTRACT(MONTH FROM evenements.date)'), [12, 1, 2]);
                });
            } elseif ($trimestre === 3) {
                $query->where(function ($q) {
                    $q->whereMonth('evenements.date', '>=', 3)
                      ->whereMonth('evenements.date', '<=', 5);
                });
            } elseif ($trimestre === 4) {
                $query->where(function ($q) {
                    $q->whereMonth('evenements.date', '>=', 6)
                      ->whereMonth('evenements.date', '<=', 8);
                });
            }
        }

        //4. Exécuter la requête principale
        $stats = $query->first();

        //5. Évolution journalière (pour graphique)
        $jours = $request->integer('jours', 30); // Nombre de jours personnalisable, défaut 30
        $evolutionQuery = Presence::selectRaw('DATE(presences.heure_scan) as date, COUNT(*) as total')
            ->join('evenements', 'presences.evenement_id', '=', 'evenements.id')
            ->join('ecs', 'evenements.ec_id', '=', 'ecs.id')
            ->join('ues', 'ecs.ue_id', '=', 'ues.id');

        // Scope établissement pour l'évolution
        if ($etablissementId) {
            $evolutionQuery->whereExists(function ($q) use ($etablissementId) {
                $q->selectRaw('1')->from('filieres')
                  ->whereColumn('filieres.id', 'ues.filiere_id')
                  ->where('filieres.etablissement_id', $etablissementId);
            });
        }

        if ($request->filled('filiere_id')) {
            $evolutionQuery->where('evenements.filiere_id', $request->integer('filiere_id'));
        }
        if ($request->filled('annee_id')) {
            $evolutionQuery->where('evenements.annee_id', $request->integer('annee_id'));
        }
        if ($request->filled('semestre')) {
            $evolutionQuery->where('ues.semestre', $request->integer('semestre'));
        }
        if ($request->filled('ue_id')) {
            $evolutionQuery->where('ecs.ue_id', $request->integer('ue_id'));
        }
        if ($request->filled('ec_id')) {
            $evolutionQuery->where('evenements.ec_id', $request->integer('ec_id'));
        }
        if ($request->filled('trimestre')) {
            $trimestre = $request->integer('trimestre');
            // Même logique que ci-dessus
            if ($trimestre === 1) {
                $evolutionQuery->whereMonth('evenements.date', '>=', 9)->whereMonth('evenements.date', '<=', 11);
            } elseif ($trimestre === 2) {
                $evolutionQuery->whereIn(DB::raw('EXTRACT(MONTH FROM evenements.date)'), [12, 1, 2]);
            } elseif ($trimestre === 3) {
                $evolutionQuery->whereMonth('evenements.date', '>=', 3)->whereMonth('evenements.date', '<=', 5);
            } elseif ($trimestre === 4) {
                $evolutionQuery->whereMonth('evenements.date', '>=', 6)->whereMonth('evenements.date', '<=', 8);
            }
        }

        $evolution = $evolutionQuery
            ->where('presences.heure_scan', '>=', now()->subDays($jours))
            ->groupBy(DB::raw('DATE(presences.heure_scan)'))
            ->orderBy('date')
            ->get();

        //6. Stats par UE (pour graphique à barres)
        $statsParUeQuery = Ue::select('ues.id', 'ues.code', 'ues.intitule', 'ues.semestre',
                DB::raw('COUNT(DISTINCT presences.id) as total_presences'),
                DB::raw('COUNT(DISTINCT evenements.id) as total_evenements'))
            ->join('ecs', 'ecs.ue_id', '=', 'ues.id')
            ->join('evenements', 'evenements.ec_id', '=', 'ecs.id')
            ->leftJoin('presences', 'presences.evenement_id', '=', 'evenements.id');

        // Scope établissement pour les stats par UE
        if ($etablissementId) {
            $statsParUeQuery->whereExists(function ($q) use ($etablissementId) {
                $q->selectRaw('1')->from('filieres')
                  ->whereColumn('filieres.id', 'ues.filiere_id')
                  ->where('filieres.etablissement_id', $etablissementId);
            });
        }

        if ($request->filled('filiere_id')) {
            $statsParUeQuery->where('ues.filiere_id', $request->integer('filiere_id'));
        }
        if ($request->filled('annee_id')) {
            $statsParUeQuery->where('ues.annee_id', $request->integer('annee_id'));
        }
        if ($request->filled('semestre')) {
            $statsParUeQuery->where('ues.semestre', $request->integer('semestre'));
        }

        $statsParUe = $statsParUeQuery
            ->groupBy('ues.id', 'ues.code', 'ues.intitule', 'ues.semestre')
            ->orderBy('ues.code')
            ->get()
            ->map(function ($ue) {
                $totalEtudiants = Etudiant::whereHas('ecs', fn($q) => $q->where('ue_id', $ue->id))->count();
                $totalAttendus = ($ue->total_evenements ?? 0) * max($totalEtudiants, 1);
                return [
                    'ue_id'           => $ue->id,
                    'code'            => $ue->code,
                    'intitule'        => $ue->intitule,
                    'semestre'        => (int) $ue->semestre,
                    'total_presences' => (int) $ue->total_presences,
                    'total_evenements' => (int) $ue->total_evenements,
                    'taux'            => $totalAttendus > 0 ? round(($ue->total_presences / $totalAttendus) * 100, 1) : 0,
                ];
            });

        //7. Calcul du taux global
        $totalPresences = (int) ($stats->total_presences ?? 0);
        $totalEvenements = (int) ($stats->total_evenements ?? 0);
        $etudiantBaseQuery = Etudiant::query();
        if ($etablissementId) {
            $etudiantBaseQuery->whereHas('filiere', fn($q) => $q->where('etablissement_id', $etablissementId));
        }
        $totalEtudiants = $etudiantBaseQuery->count();

        // Si un filtre filière est actif, compter les étudiants de cette filière
        if ($request->filled('filiere_id')) {
            $totalEtudiants = Etudiant::where('filiere_id', $request->integer('filiere_id'))->count();
        }

        $tauxGlobal = $totalEvenements > 0 && $totalEtudiants > 0
            ? round(($totalPresences / ($totalEvenements * max($totalEtudiants, 1))) * 100, 1)
            : 0;

        //8. Retourner la réponse
        return $this->successResponse([
            'taux_global'       => $tauxGlobal,
            'total_presences'   => $totalPresences,
            'total_evenements'  => $totalEvenements,
            'total_etudiants'   => $totalEtudiants,
            'presences_valides' => (int) ($stats->presences_valides ?? 0),
            'presences_suspectes' => (int) ($stats->presences_suspectes ?? 0),
            'evolution'         => $evolution,
            'stats_par_ue'      => $statsParUe,
            'filtres_appliques' => [
                'filiere_id'  => $request->filled('filiere_id') ? $request->integer('filiere_id') : null,
                'annee_id'    => $request->filled('annee_id') ? $request->integer('annee_id') : null,
                'semestre'    => $request->filled('semestre') ? $request->integer('semestre') : null,
                'trimestre'   => $request->filled('trimestre') ? $request->integer('trimestre') : null,
                'ue_id'       => $request->filled('ue_id') ? $request->integer('ue_id') : null,
                'ec_id'       => $request->filled('ec_id') ? $request->integer('ec_id') : null,
            ],
        ]);
    }

    /**
     * Export Excel des données de présence.
     * GET /api/admin/reports/excel/export
     */
    public function excelExport(Request $request): mixed
    {
        $query = Presence::with(['etudiant.filiere', 'evenement.ec']);

        // Scope par établissement via l'étudiant → filière
        $this->scopeViaRelation($query, $request, 'etudiant.filiere');

        if ($request->filled('filiere_id')) {
            $query->whereHas('etudiant', fn($q) => $q->where('filiere_id', $request->filiere_id));
        }
        if ($request->filled('date_debut')) {
            $query->whereDate('heure_scan', '>=', $request->date_debut);
        }
        if ($request->filled('date_fin')) {
            $query->whereDate('heure_scan', '<=', $request->date_fin);
        }

        $presences = $query->orderBy('heure_scan')->get();

        $filename = "export_presences_" . now()->format('Ymd_His') . ".csv";

        $headers = [
            'Content-Type'              => 'text/csv; charset=UTF-8',
            'Content-Disposition'       => "attachment; filename={$filename}",
        ];

        $callback = function () use ($presences) {
            $output = fopen('php://output', 'w');
            fputs($output, "\xEF\xBB\xBF");

            fputcsv($output, ['Étudiant', 'Matricule', 'Filière', 'Cours', 'Date', 'Heure Scan', 'Statut', 'IP']);

            foreach ($presences as $p) {
                fputcsv($output, [
                    ($p->etudiant->nom ?? '') . ' ' . ($p->etudiant->prenom ?? ''),
                    $p->etudiant->matricule ?? 'N/A',
                    $p->etudiant->filiere?->code ?? 'N/A',
                    $p->evenement->ec?->intitule ?? 'N/A',
                    $p->evenement->date?->format('Y-m-d') ?? 'N/A',
                    $p->heure_scan?->format('Y-m-d H:i:s') ?? 'N/A',
                    $p->statut,
                    $p->ip_address ?? '',
                ]);
            }

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }
}
