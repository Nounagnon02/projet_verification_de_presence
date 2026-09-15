<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Ec;
use App\Models\Etudiant;
use App\Models\Presence;
use App\Models\User;
use App\Services\CriteresExport;
use App\Traits\ScopedByEtablissement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PresenceHistoryController extends Controller
{
    use ScopedByEtablissement;

    private const LIBELLES_ORIGINE = [
        'scan'                   => 'Scan',
        'validee_apres_examen'   => 'Validé après examen',
        'rejetee_apres_examen'   => 'Rejeté après examen',
        'saisie_manuelle'        => 'Saisie manuelle',
        'rattrapage_scan_refuse' => "Rattrapage d'un scan refusé",
        'decision_non_tracee'    => 'Décision non tracée',
    ];

    /**
     * Construit la requête de base avec les filtres (réutilisable par index et export).
     */
    private function buildFilteredQuery(Request $request)
    {
        $query = Presence::with(['etudiant.filiere', 'evenement.ec']);

        // Scope par établissement via l'étudiant → filière
        $this->scopeViaRelation($query, $request, 'etudiant.filiere');

        // Insensible à la casse, et « prénom nom » comme à l'écran : avec like,
        // PostgreSQL distinguait les majuscules et « soton » ne trouvait pas SOTON.
        if ($request->filled('search')) {
            $terme = '%' . addcslashes(trim((string) $request->search), '%_\\') . '%';
            $query->whereHas('etudiant', fn ($q) => $q->where(fn ($q) => $q
                ->where('nom', 'ilike', $terme)
                ->orWhere('prenom', 'ilike', $terme)
                ->orWhere('matricule', 'ilike', $terme)
                ->orWhereRaw("prenom || ' ' || nom ilike ?", [$terme])));
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

        if ($request->filled('annee_id')) {
            $query->whereHas('evenement', fn($q) => $q->where('annee_id', $request->annee_id));
        }

        if ($request->filled('niveau')) {
            $query->whereHas('etudiant.filiere', fn($q) => $q->where('niveau', $request->niveau));
        }

        if ($request->filled('semestre')) {
            $query->whereHas('evenement.ec.ue', fn($q) => $q->where('semestre', $request->semestre));
        }

        return $query;
    }

    /**
     * Tri demandé : date (par défaut, plus récente d'abord), étudiant ou cours.
     * Il porte sur toute la sélection : trié dans le navigateur, il ne
     * rangeait que la page affichée — et il n'était d'ailleurs pas branché.
     */
    private function appliquerTri($query, Request $request)
    {
        $sens = $request->query('sens') === 'asc' ? 'asc' : 'desc';

        match ($request->query('tri')) {
            'etudiant' => $query
                ->orderBy(Etudiant::select('nom')->whereColumn('etudiants.id', 'presences.etudiant_id'), $sens)
                ->orderBy(Etudiant::select('prenom')->whereColumn('etudiants.id', 'presences.etudiant_id'), $sens),
            'cours'    => $query->orderBy(
                Ec::select('ecs.intitule')
                    ->join('evenements', 'evenements.ec_id', '=', 'ecs.id')
                    ->whereColumn('evenements.id', 'presences.evenement_id'),
                $sens
            ),
            default    => $query->orderBy('heure_scan', $sens),
        };

        // Départage stable : à valeur égale, le plus récent d'abord.
        return $query->orderByDesc('heure_scan')->orderByDesc('id');
    }

    /**
     * Origine de chaque présence, et qui en a décidé.
     *
     * Un scan, une présence validée ou rejetée après examen, une saisie
     * manuelle et un rattrapage de scan refusé se présentaient à l'identique.
     * L'action est lue au journal d'audit (la plus récente) ; à défaut, dans
     * les champs d'arbitrage de la présence.
     *
     * @return array<int, array{origine: string, libelle: string, decide_par: ?string, motif: ?string}>
     */
    private function origines(Collection $presences): array
    {
        // Triées par identifiant : keyBy garde la dernière action de chaque présence.
        $actions = AuditLog::where('model_type', Presence::class)
            ->whereIn('model_id', $presences->pluck('id'))
            ->where('action', 'like', 'presence.%')
            ->orderBy('id')
            ->get(['model_id', 'action', 'user_id'])
            ->keyBy('model_id');

        $auteurs = User::whereIn('id', $actions->pluck('user_id')->merge($presences->pluck('validated_by'))->filter()->unique()->values())
            ->pluck('name', 'id');

        return $presences->mapWithKeys(function (Presence $p) use ($actions, $auteurs) {
            $action = $actions->get($p->id);

            $origine = match ($action?->action) {
                'presence.validate_manual'       => 'validee_apres_examen',
                'presence.reject_manual'         => 'rejetee_apres_examen',
                'presence.saisie_manuelle'       => 'saisie_manuelle',
                'presence.enregistrement_manuel' => 'rattrapage_scan_refuse',
                default => match (true) {
                    $p->validated_by !== null && $p->statut === 'rejete' => 'rejetee_apres_examen',
                    $p->validated_by !== null                            => 'validee_apres_examen',
                    // Rejetée sans trace de qui l'a décidé : données anciennes.
                    $p->statut === 'rejete'                              => 'decision_non_tracee',
                    default                                              => 'scan',
                },
            };

            $decision = $origine !== 'scan';

            return [$p->id => [
                'origine'    => $origine,
                'libelle'    => self::LIBELLES_ORIGINE[$origine],
                'decide_par' => $decision ? ($auteurs[$action?->user_id ?? $p->validated_by] ?? null) : null,
                'motif'      => $decision ? $p->validation_motif : null,
            ]];
        })->all();
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->buildFilteredQuery($request);

        $perPage = min((int) $request->per_page, 100);
        $presences = $this->appliquerTri($query, $request)->paginate($perPage ?: 15);
        $origines = $this->origines($presences->getCollection());

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
                    'id'          => $p->evenement->id,
                    'cours'       => $p->evenement->ec?->intitule ?? 'N/A',
                    'date'        => $p->evenement->date?->format('Y-m-d'),
                    'heure_debut' => substr((string) $p->evenement->heure_debut, 0, 5),
                    'heure_fin'   => substr((string) $p->evenement->heure_fin, 0, 5),
                ],
                'heure_scan' => $p->heure_scan->format('Y-m-d H:i:s'),
                'statut'     => $p->statut,
                'origine'    => $origines[$p->id],
                'ip_address' => $p->ip_address,
            ])
        );
    }

    /**
     * Export des présences filtrées au format CSV, PDF ou Excel.
     *
     * GET /api/admin/presence/export?format=csv|pdf|xlsx
     */
    public function export(Request $request, CriteresExport $descripteur): mixed
    {
        $format = $request->query('format', 'csv');
        if (!in_array($format, ['csv', 'pdf', 'xlsx'])) {
            $format = 'csv';
        }

        $query = $this->buildFilteredQuery($request);
        // Le tri choisi à l'écran s'il y en a un ; sinon l'ordre chronologique.
        $presences = ($request->filled('tri') ? $this->appliquerTri($query, $request) : $query->orderBy('heure_scan'))->get();
        $origines = $this->origines($presences);

        // Critères : le fichier doit dire sur quoi il porte, et quels semestres
        // couvrent les présences exportées.
        $presences->loadMissing('evenement.ec.ue:id,semestre');
        $semestres = $presences->map(fn ($p) => $p->evenement?->ec?->ue?->semestre);
        $criteres = $descripteur->decrire($request, $this->getEtablissementId($request), $semestres);
        $contexte = [
            'criteres'   => $criteres,
            'exporte_le' => now()->format('d/m/Y à H:i'),
        ];
        $nom = fn (string $extension) => $descripteur->nomFichier('historique', $criteres, $extension);

        return match ($format) {
            'pdf'  => $this->exportPdf($presences, $origines, $contexte, $nom('pdf')),
            'xlsx' => $this->exportXlsx($presences, $origines, $contexte, $nom('xlsx')),
            // Le CSV reste de la donnée pure : ses critères sont dans son nom.
            default => $this->exportCsv($presences, $origines, $nom('csv')),
        };
    }

    private function exportCsv($presences, array $origines, string $nomFichier): mixed
    {
        $filename = $nomFichier;

        $headers = [
            'Content-Type'              => 'text/csv; charset=UTF-8',
            'Content-Disposition'       => "attachment; filename={$filename}",
        ];

        $callback = function () use ($presences, $origines) {
            $output = fopen('php://output', 'w');
            fputs($output, "\xEF\xBB\xBF"); // BOM UTF-8

            fputcsv($output, ['Étudiant', 'Prénom', 'Nom', 'Matricule', 'Filière', 'Cours', 'Date', 'Heure Scan', 'Statut', 'Origine', 'Décidé par', 'Motif', 'IP']);

            foreach ($presences as $p) {
                fputcsv($output, [
                    ($p->etudiant->prenom ?? '') . ' ' . ($p->etudiant->nom ?? ''),
                    $p->etudiant->prenom ?? '',
                    $p->etudiant->nom ?? '',
                    $p->etudiant->matricule ?? 'N/A',
                    $p->etudiant->filiere?->code ?? 'N/A',
                    $p->evenement->ec?->intitule ?? 'N/A',
                    $p->evenement->date?->format('Y-m-d') ?? 'N/A',
                    $p->heure_scan?->format('Y-m-d H:i:s') ?? 'N/A',
                    Presence::LIBELLES_STATUT[$p->statut] ?? $p->statut,
                    $origines[$p->id]['libelle'],
                    $origines[$p->id]['decide_par'] ?? '',
                    $origines[$p->id]['motif'] ?? '',
                    $p->ip_address ?? '',
                ]);
            }

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportPdf($presences, array $origines, array $contexte, string $nomFichier): mixed
    {
        $pdf = Pdf::loadView('reports.history', [
            'presences'      => $presences,
            'origines'       => $origines,
            'libellesStatut' => Presence::LIBELLES_STATUT,
            'contexte'       => $contexte,
            'date'           => now()->format('d/m/Y H:i'),
            'title'          => 'Historique des Présences',
            'total'          => $presences->count(),
        ]);

        return $pdf->download($nomFichier);
    }

    private function exportXlsx($presences, array $origines, array $contexte, string $nomFichier): mixed
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Présences');

        // Critères en tête : le tableau seul ne disait pas sur quoi il portait.
        $ligne = 1;
        $sheet->setCellValue("A{$ligne}", 'Historique des présences');
        $sheet->getStyle("A{$ligne}")->getFont()->setBold(true)->setSize(14);
        $ligne++;
        $sheet->setCellValue("A{$ligne}", "Exporté le {$contexte['exporte_le']} — {$presences->count()} présence(s)");
        $ligne++;

        $criteres = array_merge(
            [['Entité', $contexte['criteres']['entite']]],
            $contexte['criteres']['semestres'] ? [['Semestre(s)', $contexte['criteres']['semestres']]] : [],
            $contexte['criteres']['lignes'],
            $contexte['criteres']['filtre'] ? [] : [['Filtres', 'Aucun filtre']],
        );
        foreach ($criteres as $critere) {
            $sheet->setCellValue("A{$ligne}", $critere[0]);
            $sheet->setCellValue("B{$ligne}", $critere[1]);
            $sheet->getStyle("A{$ligne}")->getFont()->setBold(true);
            $ligne++;
        }

        $entete = $ligne + 1; // une ligne vide avant le tableau

        $headers = ['Étudiant', 'Prénom', 'Nom', 'Matricule', 'Filière', 'Cours', 'Date', 'Heure Scan', 'Statut', 'Origine', 'Décidé par', 'Motif', 'IP'];
        $colLetters = range('A', 'M');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E40AF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        foreach ($colLetters as $i => $col) {
            $sheet->setCellValue($col . $entete, $headers[$i]);
            $sheet->getStyle($col . $entete)->applyFromArray($headerStyle);
        }

        $row = $entete + 1;
        foreach ($presences as $p) {
            $sheet->setCellValue('A' . $row, ($p->etudiant->prenom ?? '') . ' ' . ($p->etudiant->nom ?? ''));
            $sheet->setCellValue('B' . $row, $p->etudiant->prenom ?? '');
            $sheet->setCellValue('C' . $row, $p->etudiant->nom ?? '');
            $sheet->setCellValue('D' . $row, $p->etudiant->matricule ?? 'N/A');
            $sheet->setCellValue('E' . $row, $p->etudiant->filiere?->code ?? 'N/A');
            $sheet->setCellValue('F' . $row, $p->evenement->ec?->intitule ?? 'N/A');
            $sheet->setCellValue('G' . $row, $p->evenement->date?->format('Y-m-d') ?? 'N/A');
            $sheet->setCellValue('H' . $row, $p->heure_scan?->format('Y-m-d H:i:s') ?? 'N/A');
            $sheet->setCellValue('I' . $row, Presence::LIBELLES_STATUT[$p->statut] ?? $p->statut);
            $sheet->setCellValue('J' . $row, $origines[$p->id]['libelle']);
            $sheet->setCellValue('K' . $row, $origines[$p->id]['decide_par'] ?? '');
            $sheet->setCellValue('L' . $row, $origines[$p->id]['motif'] ?? '');
            $sheet->setCellValue('M' . $row, $p->ip_address ?? '');

            // Alternance de couleurs pour les lignes
            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':M' . $row)
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->setStartColor(new Color('FFF3F4F6'));
            }

            $row++;
        }

        // Filtres du tableur sur le tableau, et en-tête figé au défilement.
        $sheet->setAutoFilter("A{$entete}:M" . max($entete, $row - 1));
        $sheet->freezePane('A' . ($entete + 1));

        // Colonne A à largeur fixe : le titre et la ligne d'export, qui y
        // débordent, l'élargiraient démesurément.
        $sheet->getColumnDimension('A')->setWidth(30);
        foreach (array_slice($colLetters, 1) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        $headers = [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename={$nomFichier}",
        ];

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, $headers);
    }

    public function stats(Request $request): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);

        // Scoper les requêtes si admin faculté
        $etudiantQuery = Etudiant::query();
        $presenceQuery = Presence::query();
        $evenementQuery = DB::table('evenements');

        if ($etablissementId) {
            $etudiantQuery->whereHas('filiere', fn($q) => $q->where('etablissement_id', $etablissementId));
            $presenceQuery->whereHas('etudiant.filiere', fn($q) => $q->where('etablissement_id', $etablissementId));
            $evenementQuery->join('filieres', 'evenements.filiere_id', '=', 'filieres.id')
                ->where('filieres.etablissement_id', $etablissementId);
        }

        $totalEtudiants = $etudiantQuery->count();
        $totalPresences = $presenceQuery->count();
        $totalEvenements = $evenementQuery->count();

        // Présences par jour — avec scope
        $presencesParJourQuery = Presence::select(
            DB::raw("DATE(heure_scan) as date"),
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) as valides"),
            DB::raw("SUM(CASE WHEN statut = 'suspect' THEN 1 ELSE 0 END) as suspectes")
        )
            ->where('heure_scan', '>=', now()->subDays(30));

        if ($etablissementId) {
            $presencesParJourQuery->whereHas('etudiant.filiere', fn($q) => $q->where('etablissement_id', $etablissementId));
        }

        $presencesParJour = $presencesParJourQuery
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Stats par filière — scope
        $statsParFiliereQuery = Etudiant::select('filieres.code', 'filieres.intitule', DB::raw('COUNT(presences.id) as total_presences'))
            ->join('filieres', 'etudiants.filiere_id', '=', 'filieres.id')
            ->leftJoin('presences', 'etudiants.id', '=', 'presences.etudiant_id');

        if ($etablissementId) {
            $statsParFiliereQuery->where('filieres.etablissement_id', $etablissementId);
        }

        $statsParFiliere = $statsParFiliereQuery
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

    public function studentStats(Request $request, Etudiant $student): JsonResponse
    {
        // Vérifier que l'admin a accès à cet étudiant (scope établissement)
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId && $student->filiere?->etablissement_id !== $etablissementId) {
            return $this->errorResponse('Étudiant non trouvé.', 404);
        }

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
