<?php

declare(strict_types=1);

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
use App\Services\CriteresExport;
use App\Services\AttendanceRateService;
use Carbon\Carbon;
use Closure;


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

        // La feuille d'émargement (noms, matricules, heures de scan) n'était
        // protégée que par le rôle : tout admin de faculté pouvait télécharger
        // celle de n'importe quelle séance de l'université en parcourant les ID.
        $this->authorizeEtablissement($evenement, $request, 'filiere');

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
    public function departmentReport(Request $request, Filiere $filiere, \App\Services\AttendanceRateService $attendance): mixed
    {
        // Filière liée par la route : sans ce contrôle, le rapport (et sa
        // version PDF) d'une filière d'une autre faculté était servi tel quel.
        $this->authorizeEtablissement($filiere, $request);

        $totalEtudiants = Etudiant::where('filiere_id', $filiere->id)->count();
        $totalEvenements = Evenement::whereHas('ec.ue.filieres', fn ($q) => $q->where('filieres.id', $filiere->id))->where('date', '<', now())->count();

        // Périmètre : événements passés de cette filière. Le taux est calculé
        // sur les étudiants réellement inscrits aux ECs concernés, pas sur
        // « tous les étudiants de la filière × tous les événements ».
        $filtre = function ($q) use ($filiere) {
            $this->filtrerFiliere($q, $filiere->id);
            $q->where('e.date', '<', now());
        };

        $presences = $attendance->recorded($filtre);
        $taux      = $attendance->rate($filtre);

        $presencesParCours = Evenement::whereHas('ec.ue.filieres', fn ($q) => $q->where('filieres.id', $filiere->id))
            ->with('ec')
            ->withCount(['presences' => fn ($q) => $q->whereHas('etudiant', fn ($e) => $e->where('filiere_id', $filiere->id))])
            ->orderBy('date', 'desc')
            ->take(20)
            ->get()
            ->map(fn($e) => [
                'cours'          => $e->ec?->intitule ?? 'N/A',
                'date'           => $e->date?->format('Y-m-d'),
                'presences_count' => $e->presences_count,
            ]);

        $donnees = [
            'filiere'            => ['id' => $filiere->id, 'code' => $filiere->code, 'intitule' => $filiere->intitule],
            'total_etudiants'    => $totalEtudiants,
            'total_evenements'   => $totalEvenements,
            'total_presences'    => $presences,
            'taux_presence'      => $taux,
            'presences_par_cours' => $presencesParCours,
        ];

        // L'interface propose un « Rapport Filiere PDF ». Sans ce rendu, elle
        // telechargeait la reponse JSON sous un nom de fichier .pdf : le fichier
        // obtenu ne s'ouvrait dans aucun lecteur.
        if ($request->query('format') === 'pdf') {
            $pdf = Pdf::loadView('reports.department', $donnees + [
                'date'  => now()->format('d/m/Y H:i'),
                'title' => 'Rapport de presence par filiere',
            ]);

            return $pdf->download(
                'rapport_filiere_' . $filiere->code . '_' . now()->format('Ymd_His') . '.pdf'
            );
        }

        return $this->successResponse($donnees);
    }

    /**
     * Rapport de présence par semestre/année académique.
     * GET /api/admin/reports/semester/{anneeAcademique}?filiere_id=X&semestre=N
     *
     * Query params optionnels :
     *   - filiere_id : filtrer par filière
     *   - semestre   : filtrer par numéro de semestre (1-10)
     */
    public function semesterReport(Request $request, AnneeAcademique $anneeAcademique, \App\Services\AttendanceRateService $attendance): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);

        // Filtre de base sur les événements de l'année (+ établissement et
        // filtres optionnels). Sert à recalculer les taux sur les présences
        // réellement attendues plutôt que « événements × étudiants ».
        $yearId = $anneeAcademique->id;
        $baseFilter = function ($q) use ($yearId, $etablissementId, $request) {
            $q->join('ecs as ec', 'ec.id', '=', 'e.ec_id')
              ->join('ues as u', 'u.id', '=', 'ec.ue_id')
              ->where('u.annee_id', $yearId);
            if ($etablissementId) {
                $q->join('filieres as f', 'f.id', '=', 'e.filiere_id')
                  ->where('f.etablissement_id', $etablissementId);
            }
            if ($request->filled('filiere_id')) {
                $this->filtrerFiliere($q, $request->integer('filiere_id'));
            }
            if ($request->filled('semestre')) {
                $q->where('u.semestre', $request->integer('semestre'));
            }
        };

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
            $query->whereIn('ues.id', fn ($p) => $p->select('ue_id')->from('ue_filiere')->where('filiere_id', $request->integer('filiere_id')));
        }

        // Filtre optionnel par semestre
        if ($request->filled('semestre')) {
            $query->where('ues.semestre', $request->integer('semestre'));
        }

        $statsParSemestre = $query->get()->map(function ($row) use ($attendance, $baseFilter) {
            $semestre = (int) $row->semestre;
            // Taux réel du semestre : présences attendues = étudiants inscrits
            // à l'EC de chaque événement du semestre.
            $filtreSemestre = function ($q) use ($baseFilter, $semestre) {
                $baseFilter($q);
                $q->where('u.semestre', $semestre);
            };
            return [
                'semestre'         => $semestre,
                'label'            => 'S' . $row->semestre,
                'taux'             => $attendance->rate($filtreSemestre),
                'total_presences'  => (int) $row->total_presences,
                'total_evenements' => (int) $row->total_evenements,
                'total_etudiants'  => (int) $row->total_etudiants,
            ];
        });

        // Totaux globaux
        $totalPresences = $statsParSemestre->sum('total_presences');
        $totalEvenements = $statsParSemestre->sum('total_evenements');
        // Comptait sur TOUTE la base : un admin de faculte lisait l'effectif
        // de l'universite entiere, pas celui de son etablissement.
        $totalEtudiants = Etudiant::where('annee_id', $anneeAcademique->id)
            ->when($etablissementId, fn ($q) => $q->whereHas('filiere', fn ($f) => $f->where('etablissement_id', $etablissementId)))
            ->count();

        $filieres = Filiere::select('filieres.id', 'filieres.code', 'filieres.intitule', 'filieres.niveau',
                DB::raw('COUNT(DISTINCT presences.id) as total_presences'),
                DB::raw('COUNT(DISTINCT evenements.id) as total_evenements'))
            ->join('ue_filiere as uf', 'uf.filiere_id', '=', 'filieres.id')
            ->join('ues', 'ues.id', '=', 'uf.ue_id')
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
            ->map(function ($f) use ($attendance, $yearId) {
                // Taux réel par filière pour cette année (corrige aussi un bug :
                // l'ancien code comparait annee_id au modèle de route, pas à son id).
                $filtreFiliere = function ($q) use ($f, $yearId) {
                    $q->join('ecs as ec', 'ec.id', '=', 'e.ec_id')
                      ->join('ues as u', 'u.id', '=', 'ec.ue_id')
                      ->where('u.annee_id', $yearId);
                    $this->filtrerFiliere($q, $f->id);
                };
                return [
                    'id'               => $f->id,
                    'code'             => $f->code,
                    'intitule'         => $f->intitule,
                    'niveau'           => $f->niveau,
                    'taux'             => $attendance->rate($filtreFiliere),
                    'total_presences'  => $attendance->recorded($filtreFiliere),
                ];
            });

        return $this->successResponse([
            'annee_academique'     => ['id' => $anneeAcademique->id, 'annee' => $anneeAcademique->libelle],
            'total_etudiants'      => $totalEtudiants,
            'total_evenements'     => $totalEvenements,
            'total_presences'      => $totalPresences,
            'taux_presence'        => $attendance->rate($baseFilter),
            'stats_par_semestre'   => $statsParSemestre,
            'stats_par_filiere'    => $filieres,
        ]);
    }

    /**
     * Taux par semestre d'une filière, sur une année.
     *
     * Même définition que le reste des rapports (AttendanceRateService) :
     * présences valides ÷ présences attendues, sur les séances terminées.
     * L'ancien calcul multipliait les séances du semestre par tous ses inscrits
     * et comptait les scans rejetés : 5,4 % pour un S1 dont la filière
     * affichait 17,3 % au classement.
     *
     * GET /api/admin/reports/semester-comparison?filiere_id=X&annee_id=Y
     */
    public function semesterComparison(Request $request, AttendanceRateService $attendance): JsonResponse
    {
        $anneeId = $request->integer('annee_id');
        $filiereId = $request->integer('filiere_id');

        if (!$anneeId || !$filiereId) {
            return $this->errorResponse('Les paramètres annee_id et filiere_id sont requis.', 422);
        }

        $filiere = Filiere::findOrFail($filiereId);

        // La filière vient du client : sans ce contrôle, un autre établissement
        // lisait ses taux.
        $this->authorizeEtablissement($filiere, $request);

        $perimetre = function ($q) use ($anneeId, $filiereId) {
            $q->join('ecs as c', 'c.id', '=', 'e.ec_id')
              ->join('ues as u', 'u.id', '=', 'c.ue_id')
              ->where('e.statut', 'termine')
              ->where('e.annee_id', $anneeId);
            $this->filtrerFiliere($q, $filiereId);
        };

        $seances = DB::table('evenements as e')->whereNull('e.deleted_at')->tap($perimetre)
            ->selectRaw('u.semestre as cle, COUNT(*) as n')->groupBy('u.semestre')->pluck('n', 'cle');
        $attendus = $attendance->expectedBy($perimetre, 'u.semestre');
        $presents = $attendance->recordedBy($perimetre, 'u.semestre');

        // Les semestres du niveau, et ceux où la filière a réellement eu cours.
        $semestres = collect(app(SemesterService::class)->getSemestersForFiliere($filiere))
            ->merge($seances->keys()->map(fn ($s) => (int) $s))
            ->unique()
            ->sort()
            ->values();

        $data = $semestres->map(function (int $semestre) use ($seances, $attendus, $presents) {
            $attendu = $attendus[$semestre] ?? 0;
            $present = $presents[$semestre] ?? 0;

            return [
                'semestre'            => $semestre,
                'label'               => "S{$semestre}",
                'taux'                => $attendu > 0 ? round(($present / $attendu) * 100, 1) : 0.0,
                'presences_attendues' => $attendu,
                'total_presences'     => $present,
                'total_evenements'    => (int) ($seances[$semestre] ?? 0),
            ];
        })->all();

        return $this->successResponse([
            'filiere'   => ['id' => $filiere->id, 'code' => $filiere->code, 'intitule' => $filiere->intitule, 'niveau' => $filiere->niveau],
            'semestres' => $data,
        ]);
    }

    /**
     * Classement des filières par taux de présence.
     *
     * Même définition que le tableau de bord et le rapport filtré : présences
     * valides ÷ présences attendues (inscrits de chaque cours), sur les
     * séances terminées. Le calcul précédent divisait toutes les présences par
     * (séances × effectif de la filière), inscrits ou non.
     *
     * GET /api/admin/reports/filiere-stats?annee_id=X
     */
    public function filiereStats(Request $request, AttendanceRateService $attendance): JsonResponse
    {
        $anneeId = $request->integer('annee_id');

        $filiereQuery = Filiere::select('filieres.*')
            ->withCount(['etudiants' => function ($q) use ($anneeId) {
                $q->where('annee_id', $anneeId);
            }]);

        $this->scopeQuery($filiereQuery, $request);
        $filieres = $filiereQuery->get();

        // Par filière de l'ÉTUDIANT : une séance d'un cours commun compte, pour
        // chaque filière, ses seuls étudiants.
        $perimetre = function ($q) use ($anneeId, $filieres) {
            $q->where('e.statut', 'termine')
              ->where('e.annee_id', $anneeId)
              ->whereIn('s.filiere_id', $filieres->pluck('id'));
        };

        $seances = DB::table('evenements as e')->whereNull('e.deleted_at')
            ->join('ecs as c', 'c.id', '=', 'e.ec_id')
            ->join('ue_filiere as uf', 'uf.ue_id', '=', 'c.ue_id')
            ->where('e.statut', 'termine')
            ->where('e.annee_id', $anneeId)
            ->whereIn('uf.filiere_id', $filieres->pluck('id'))
            ->selectRaw('uf.filiere_id as cle, COUNT(*) as n')->groupBy('uf.filiere_id')->pluck('n', 'cle');
        $attendus = $attendance->expectedBy($perimetre, 's.filiere_id');
        $presents = $attendance->recordedBy($perimetre, 's.filiere_id');

        $lignes = $filieres->map(function ($filiere) use ($seances, $attendus, $presents) {
            $attendu = $attendus[$filiere->id] ?? 0;
            $present = $presents[$filiere->id] ?? 0;

            return [
                'id'                  => $filiere->id,
                'code'                => $filiere->code,
                'intitule'            => $filiere->intitule,
                'niveau'              => $filiere->niveau,
                'etudiants_count'     => $filiere->etudiants_count ?? 0,
                'taux'                => $attendu > 0 ? round(($present / $attendu) * 100, 1) : 0.0,
                'presences_attendues' => $attendu,
                'total_presences'     => $present,
                'total_evenements'    => (int) ($seances[$filiere->id] ?? 0),
            ];
        })
            ->sortByDesc('taux')
            ->values();

        return $this->successResponse($lignes);
    }

    /**
     * Taux de chaque année académique de l'entité, sur les séances terminées.
     *
     * La page Rapports interrogeait pour cela le rapport semestriel, année par
     * année : son taux ignorait le statut des séances, si bien qu'une séance
     * à venir y comptait déjà comme une absence.
     *
     * GET /api/admin/reports/annee-stats
     */
    public function anneeStats(Request $request, AttendanceRateService $attendance): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);

        $perimetre = function ($q) use ($etablissementId) {
            $q->where('e.statut', 'termine');

            if ($etablissementId) {
                $q->whereIn('e.filiere_id', fn ($f) => $f->select('id')->from('filieres')->where('etablissement_id', $etablissementId));
            }
        };

        $seances = DB::table('evenements as e')->whereNull('e.deleted_at')->tap($perimetre)
            ->selectRaw('e.annee_id as cle, COUNT(*) as n')->groupBy('e.annee_id')->pluck('n', 'cle');
        $attendus = $attendance->expectedBy($perimetre, 'e.annee_id');
        $presents = $attendance->recordedBy($perimetre, 'e.annee_id');

        // L'année active de CET établissement, pas celle de l'université.
        $activeId = AnneeAcademique::activePour($etablissementId)?->id;

        $annees = AnneeAcademique::orderBy('libelle')->get()
            ->map(function (AnneeAcademique $annee) use ($seances, $attendus, $presents, $activeId) {
                $attendu = $attendus[$annee->id] ?? 0;
                $present = $presents[$annee->id] ?? 0;

                return [
                    'id'                  => $annee->id,
                    'libelle'             => $annee->libelle,
                    'active'              => $annee->id === $activeId,
                    // Pas de taux sans séance terminée, plutôt qu'un 0 % trompeur.
                    'taux'                => $attendu > 0 ? round(($present / $attendu) * 100, 1) : null,
                    'presences_attendues' => $attendu,
                    'total_presences'     => $present,
                    'total_evenements'    => (int) ($seances[$annee->id] ?? 0),
                ];
            })
            ->values();

        return $this->successResponse($annees);
    }

    /**
     * Rapport filtré : filière, année, semestre, trimestre, UE, EC, période.
     *
     * Le taux est celui du tableau de bord (AttendanceRateService) : présences
     * valides ÷ présences attendues, sur les séances terminées. Ce rapport
     * divisait toutes les présences, rejetées comprises, par (séances ayant au
     * moins un scan × tous les étudiants de l'établissement) : 2,7 % ici pour
     * 16,4 % au tableau de bord, sur les mêmes données. Compteurs, UE et
     * évolution portent désormais tous sur le même périmètre.
     *
     * GET /api/admin/reports/filtered
     */
    public function filteredStats(Request $request, AttendanceRateService $attendance, CriteresExport $descripteur): JsonResponse
    {
        $this->validerFiltres($request);

        $perimetre = $this->perimetreSeances($request);
        $seances = fn () => DB::table('evenements as e')->whereNull('e.deleted_at')->tap($perimetre);

        $scans = DB::table('presences as p')
            ->join('evenements as e', 'e.id', '=', 'p.evenement_id')
            ->whereNull('p.deleted_at')
            ->whereNull('e.deleted_at')
            ->tap($perimetre)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN p.statut = 'suspect' THEN 1 ELSE 0 END) as suspectes")
            ->selectRaw("SUM(CASE WHEN p.statut = 'rejete' THEN 1 ELSE 0 END) as rejetees")
            ->first();

        $attendus = $attendance->expected($perimetre);
        $presents = $attendance->recorded($perimetre);

        return $this->successResponse([
            // L'en-tête de la page dit ce que l'on regarde : entité, année, période.
            'entite'              => $descripteur->decrire($request, $this->getEtablissementId($request))['entite'],
            'taux_global'         => $attendus > 0 ? round(($presents / $attendus) * 100, 1) : 0.0,
            'total_evenements'    => $seances()->count(),
            'presences_attendues' => $attendus,
            'presences_valides'   => $presents,
            'absences'            => max(0, $attendus - $presents),
            'presences_suspectes' => (int) ($scans->suspectes ?? 0),
            'presences_rejetees'  => (int) ($scans->rejetees ?? 0),
            // Tous les scans de ces séances, quel que soit leur statut.
            'total_presences'     => (int) ($scans->total ?? 0),
            'total_etudiants'     => $attendance->expectedStudents($perimetre),
            'evolution'           => $this->evolutionHebdomadaire($request, $perimetre, $seances, $attendance),
            'stats_par_ue'        => $this->statsParUe($perimetre, $attendance),
            'filtres_appliques'   => [
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
     * Séances d'un rapport, sur l'alias « e ».
     *
     * Pour un taux : les séances terminées (statut « termine », posé à la
     * fermeture du scan ; une séance annulée ne l'est jamais), comme au tableau
     * de bord. Pour un export de présences : toutes les séances. Les filtres de
     * la page s'appliquent à l'identique aux compteurs, aux UE, à l'évolution
     * et à l'export — plusieurs blocs en ignoraient une partie.
     */
    private function perimetreSeances(Request $request, bool $terminees = true): Closure
    {
        $etablissementId = $this->getEtablissementId($request);

        return function ($q) use ($request, $etablissementId, $terminees) {
            if ($terminees) {
                $q->where('e.statut', 'termine');
            }

            if ($etablissementId) {
                $q->whereIn('e.filiere_id', fn ($f) => $f->select('id')->from('filieres')->where('etablissement_id', $etablissementId));
            }

            if ($request->filled('filiere_id')) {
                $this->filtrerFiliere($q, $request->integer('filiere_id'));
            }

            if ($request->filled('annee_id')) {
                $q->where('e.annee_id', $request->integer('annee_id'));
            }

            if ($request->filled('ec_id')) {
                $q->where('e.ec_id', $request->integer('ec_id'));
            }

            if ($request->filled('ue_id') || $request->filled('semestre')) {
                $q->whereIn('e.ec_id', function ($ecs) use ($request) {
                    $ecs->select('ecs.id')->from('ecs')->join('ues', 'ues.id', '=', 'ecs.ue_id');
                    if ($request->filled('ue_id')) {
                        $ecs->where('ecs.ue_id', $request->integer('ue_id'));
                    }
                    if ($request->filled('semestre')) {
                        $ecs->where('ues.semestre', $request->integer('semestre'));
                    }
                });
            }

            // Période sur la date de la séance.
            if ($request->filled('date_debut')) {
                $q->where('e.date', '>=', $request->date_debut);
            }

            if ($request->filled('date_fin')) {
                $q->where('e.date', '<=', $request->date_fin);
            }

            // Trimestres de l'année académique, qui commence en septembre.
            $mois = [1 => [9, 10, 11], 2 => [12, 1, 2], 3 => [3, 4, 5], 4 => [6, 7, 8]][$request->integer('trimestre')] ?? null;
            if ($request->filled('trimestre') && $mois) {
                $q->whereIn(DB::raw('EXTRACT(MONTH FROM e.date)'), $mois);
            }
        };
    }

    /**
     * Taux par UE sur le même périmètre, en quatre requêtes pour toutes les UE.
     *
     * @return array<int, array<string, mixed>>
     */
    private function statsParUe(Closure $perimetre, AttendanceRateService $attendance): array
    {
        // Regroupement par l'UE de l'EC de la séance (alias « c »).
        $parUe = function ($q) use ($perimetre) {
            $q->join('ecs as c', 'c.id', '=', 'e.ec_id');
            $perimetre($q);
        };

        $seances = DB::table('evenements as e')->whereNull('e.deleted_at')->tap($parUe)
            ->selectRaw('c.ue_id as cle, COUNT(*) as n')->groupBy('c.ue_id')->pluck('n', 'cle');

        if ($seances->isEmpty()) {
            return [];
        }

        $attendus = $attendance->expectedBy($parUe, 'c.ue_id');
        $presents = $attendance->recordedBy($parUe, 'c.ue_id');
        $etudiants = $attendance->expectedStudentsBy($parUe, 'c.ue_id');

        return Ue::with('filiere:id,code,intitule')
            ->whereIn('id', $seances->keys())
            ->orderBy('code')
            ->get()
            ->map(function (Ue $ue) use ($seances, $attendus, $presents, $etudiants) {
                $attendu = $attendus[$ue->id] ?? 0;
                $present = $presents[$ue->id] ?? 0;

                return [
                    'ue_id'               => $ue->id,
                    'code'                => $ue->code,
                    'intitule'            => $ue->intitule,
                    'semestre'            => (int) $ue->semestre,
                    'filiere_code'        => $ue->filiere?->code,
                    'filiere_intitule'    => $ue->filiere?->intitule,
                    'total_evenements'    => (int) $seances[$ue->id],
                    'presences_attendues' => $attendu,
                    // Présents : présences valides.
                    'total_presences'     => $present,
                    'total_etudiants'     => $etudiants[$ue->id] ?? 0,
                    'taux'                => $attendu > 0 ? round(($present / $attendu) * 100, 1) : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Taux de présence semaine par semaine sur la période, semaines sans séance
     * comprises (taux null). L'ancien graphique comptait des scans par jour et
     * sautait les jours sans scan.
     *
     * @return array<int, array{semaine: string, seances: int, attendus: int, presents: int, taux: ?float}>
     */
    private function evolutionHebdomadaire(Request $request, Closure $perimetre, Closure $seances, AttendanceRateService $attendance): array
    {
        $bornes = $seances()->selectRaw('MIN(e.date) as debut, MAX(e.date) as fin')->first();

        // Aucune séance terminée : rien à tracer.
        if (!$bornes || !$bornes->debut) {
            return [];
        }

        $debut = Carbon::parse($request->date_debut ?: $bornes->debut)->startOfWeek(Carbon::MONDAY);
        $fin = Carbon::parse($request->date_fin ?: $bornes->fin)->startOfWeek(Carbon::MONDAY);

        if ($debut->gt($fin)) {
            return [];
        }

        $semaine = "to_char(date_trunc('week', e.date), 'YYYY-MM-DD')";
        $nbSeances = $seances()->selectRaw("{$semaine} as cle, COUNT(*) as n")->groupByRaw($semaine)->pluck('n', 'cle');
        $attendus = $attendance->expectedBy($perimetre, $semaine);
        $presents = $attendance->recordedBy($perimetre, $semaine);

        $semaines = [];
        for ($s = $debut->copy(); $s->lte($fin); $s->addWeek()) {
            $cle = $s->format('Y-m-d');
            $attendu = $attendus[$cle] ?? 0;
            $present = $presents[$cle] ?? 0;

            $semaines[] = [
                'semaine'  => $cle,
                'seances'  => (int) ($nbSeances[$cle] ?? 0),
                'attendus' => $attendu,
                'presents' => $present,
                'taux'     => $attendu > 0 ? round(($present / $attendu) * 100, 1) : null,
            ];
        }

        // Au plus un an : les semaines les plus récentes.
        return array_slice($semaines, -53);
    }

    /**
     * Étudiants ayant manqué au moins une séance du périmètre, les plus absents
     * d'abord : de quoi prévenir un étudiant ou sa filière. Mêmes filtres et
     * même définition que le rapport filtré ; « format=csv » renvoie la liste
     * entière en fichier.
     *
     * GET /api/admin/reports/etudiants-absents
     */
    public function etudiantsAbsents(Request $request, AttendanceRateService $attendance, CriteresExport $descripteur): mixed
    {
        $this->validerFiltres($request);

        $perimetre = $this->perimetreSeances($request);
        $absences = $attendance->absencesParEtudiant($perimetre);

        $etudiants = Etudiant::with('filiere:id,code')
            ->whereIn('id', array_keys($absences))
            ->get(['id', 'nom', 'prenom', 'matricule', 'filiere_id'])
            ->keyBy('id');

        $lignes = collect($absences)
            ->filter(fn ($absence, $id) => $etudiants->has($id))
            ->map(function ($absence, $id) use ($etudiants) {
                $etudiant = $etudiants[$id];

                return [
                    'etudiant_id'    => $id,
                    'nom'            => $etudiant->nom,
                    'prenom'         => $etudiant->prenom,
                    'matricule'      => $etudiant->matricule,
                    'filiere_code'   => $etudiant->filiere?->code,
                    'attendus'       => $absence['attendus'],
                    'presents'       => $absence['presents'],
                    'absences'       => $absence['absences'],
                    'taux'           => round(($absence['presents'] / $absence['attendus']) * 100, 1),
                    'dernier_manque' => $absence['dernier_manque'],
                ];
            })
            // Le plus d'absences, puis le taux le plus bas, puis le nom.
            ->sort(fn ($a, $b) => [$b['absences'], $a['taux'], $a['nom']] <=> [$a['absences'], $b['taux'], $b['nom']])
            ->values();

        if ($request->query('format') === 'csv') {
            $fichier = $descripteur->nomFichier(
                'etudiants_absents',
                $descripteur->decrire($request, $this->getEtablissementId($request)),
                'csv'
            );

            return response()->stream(function () use ($lignes) {
                $sortie = fopen('php://output', 'w');
                fputs($sortie, "\xEF\xBB\xBF");
                fputcsv($sortie, ['Étudiant', 'Matricule', 'Filière', 'Absences', 'Présents', 'Attendus', 'Taux', 'Dernier cours manqué', 'Date']);

                foreach ($lignes as $ligne) {
                    $dernier = $ligne['dernier_manque'];

                    fputcsv($sortie, [
                        trim($ligne['nom'] . ' ' . $ligne['prenom']),
                        $ligne['matricule'],
                        $ligne['filiere_code'] ?? '',
                        $ligne['absences'],
                        $ligne['presents'],
                        $ligne['attendus'],
                        $ligne['taux'] . '%',
                        $dernier['ec'] ?? '',
                        $dernier ? Carbon::parse($dernier['date'])->format('d/m/Y') : '',
                    ]);
                }

                fclose($sortie);
            }, 200, [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename={$fichier}",
            ]);
        }

        return $this->successResponse([
            'etudiants_attendus' => $attendance->expectedStudents($perimetre),
            'etudiants'          => $lignes,
        ]);
    }

    /** Filtres communs au rapport filtré et à la liste des absents. */
    private function validerFiltres(Request $request): void
    {
        $request->validate([
            'filiere_id' => 'nullable|integer',
            'annee_id'   => 'nullable|integer',
            'semestre'   => 'nullable|integer|between:1,10',
            'trimestre'  => 'nullable|integer|between:1,4',
            'ue_id'      => 'nullable|integer',
            'ec_id'      => 'nullable|integer',
            'date_debut' => 'nullable|date',
            'date_fin'   => 'nullable|date',
        ]);
    }

    /**
     * Export CSV des données de présence (la route garde son nom historique).
     *
     * Les critères appliqués sont dans le nom du fichier ; le CSV reste de la
     * donnée pure. Les colonnes suivent le choix fait à l'écran : les cases de
     * la page « Export CSV » n'étaient transmises nulle part.
     *
     * GET /api/admin/reports/excel/export
     */
    public function excelExport(Request $request, CriteresExport $descripteur): mixed
    {
        $request->validate(['date_debut' => 'nullable|date', 'date_fin' => 'nullable|date']);

        // Mêmes filtres que la page : filière, année, semestre, UE, EC, période
        // (date de la séance). Seuls la filière et les dates étaient appliqués :
        // le fichier ne correspondait pas à ce qui était affiché.
        $presences = Presence::with(['etudiant.filiere', 'evenement.ec'])
            ->whereIn('presences.evenement_id', fn ($s) => $s->select('e.id')->from('evenements as e')
                ->whereNull('e.deleted_at')
                ->tap($this->perimetreSeances($request, false)))
            ->orderBy('heure_scan')
            ->get();

        $filename = $descripteur->nomFichier('presences', $descripteur->decrire($request, $this->getEtablissementId($request)), 'csv');

        $disponibles = [
            'name'      => ['Étudiant', fn ($p) => trim(($p->etudiant->nom ?? '') . ' ' . ($p->etudiant->prenom ?? ''))],
            'matricule' => ['Matricule', fn ($p) => $p->etudiant->matricule ?? 'N/A'],
            'filiere'   => ['Filière', fn ($p) => $p->etudiant->filiere?->code ?? 'N/A'],
            'course'    => ['Cours', fn ($p) => $p->evenement->ec?->intitule ?? 'N/A'],
            'date'      => ['Date', fn ($p) => $p->evenement->date?->format('Y-m-d') ?? 'N/A'],
            'time'      => ['Heure Scan', fn ($p) => $p->heure_scan?->format('Y-m-d H:i:s') ?? 'N/A'],
            // Libellé et non valeur brute : « valide » sortait tel quel.
            'status'    => ['Statut', fn ($p) => Presence::LIBELLES_STATUT[$p->statut] ?? $p->statut],
            'ip'        => ['IP', fn ($p) => $p->ip_address ?? ''],
        ];
        $demandees = array_filter(array_map('trim', explode(',', (string) $request->query('colonnes', ''))));
        $colonnes = array_intersect_key($disponibles, array_flip($demandees)) ?: $disponibles;

        $headers = [
            'Content-Type'              => 'text/csv; charset=UTF-8',
            'Content-Disposition'       => "attachment; filename={$filename}",
        ];

        $callback = function () use ($presences, $colonnes) {
            $output = fopen('php://output', 'w');
            fputs($output, "\xEF\xBB\xBF");

            fputcsv($output, array_column($colonnes, 0));

            foreach ($presences as $p) {
                fputcsv($output, array_map(fn ($colonne) => $colonne[1]($p), array_values($colonnes)));
            }

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Séances d'une filière : celles des cours qu'elle suit, cours communs
     * compris. Et, quand la requête porte sur des étudiants (alias « s » des
     * requêtes d'AttendanceRateService), ses seuls étudiants : une séance
     * commune compte pour chaque filière avec les siens.
     */
    private function filtrerFiliere($q, int $filiereId): void
    {
        $q->whereIn('e.ec_id', fn ($c) => $c->select('ecs.id')->from('ecs')
            ->join('ue_filiere as ufl', 'ufl.ue_id', '=', 'ecs.ue_id')
            ->where('ufl.filiere_id', $filiereId));

        if (collect($q->joins ?? [])->contains(fn ($jointure) => $jointure->table === 'etudiants as s')) {
            $q->where('s.filiere_id', $filiereId);
        }
    }
}
