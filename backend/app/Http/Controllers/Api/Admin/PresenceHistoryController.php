<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use App\Models\Presence;
use App\Traits\ScopedByEtablissement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * Construit la requête de base avec les filtres (réutilisable par index et export).
     */
    private function buildFilteredQuery(Request $request)
    {
        $query = Presence::with(['etudiant.filiere', 'evenement.ec']);

        // Scope par établissement via l'étudiant → filière
        $this->scopeViaRelation($query, $request, 'etudiant.filiere');

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

    public function index(Request $request): JsonResponse
    {
        $query = $this->buildFilteredQuery($request);

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

    /**
     * Export des présences filtrées au format CSV, PDF ou Excel.
     *
     * GET /api/admin/presence/export?format=csv|pdf|xlsx
     */
    public function export(Request $request): mixed
    {
        $format = $request->query('format', 'csv');
        if (!in_array($format, ['csv', 'pdf', 'xlsx'])) {
            $format = 'csv';
        }

        $query = $this->buildFilteredQuery($request);
        $presences = $query->orderBy('heure_scan')->get();
        $dateLabel = now()->format('Y-m-d_Hi');

        return match ($format) {
            'pdf'  => $this->exportPdf($presences, $dateLabel),
            'xlsx' => $this->exportXlsx($presences, $dateLabel),
            default => $this->exportCsv($presences, $dateLabel),
        };
    }

    private function exportCsv($presences, string $dateLabel): mixed
    {
        $filename = "historique_presences_{$dateLabel}.csv";

        $headers = [
            'Content-Type'              => 'text/csv; charset=UTF-8',
            'Content-Disposition'       => "attachment; filename={$filename}",
        ];

        $callback = function () use ($presences) {
            $output = fopen('php://output', 'w');
            fputs($output, "\xEF\xBB\xBF"); // BOM UTF-8

            fputcsv($output, ['Étudiant', 'Prénom', 'Nom', 'Matricule', 'Filière', 'Cours', 'Date', 'Heure Scan', 'Statut', 'IP']);

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
                    match ($p->statut) {
                        'valide'  => 'Présent',
                        'absent'  => 'Absent',
                        'suspect' => 'Suspect',
                        'en_retard' => 'En retard',
                        default   => $p->statut,
                    },
                    $p->ip_address ?? '',
                ]);
            }

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportPdf($presences, string $dateLabel): mixed
    {
        $data = [
            'presences' => $presences,
            'date'      => now()->format('d/m/Y H:i'),
            'title'     => 'Historique des Présences',
            'total'     => $presences->count(),
        ];

        $pdf = Pdf::loadView('reports.history', $data);
        return $pdf->download("historique_presences_{$dateLabel}.pdf");
    }

    private function exportXlsx($presences, string $dateLabel): mixed
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Présences');

        // En-têtes
        $headers = ['Étudiant', 'Prénom', 'Nom', 'Matricule', 'Filière', 'Cours', 'Date', 'Heure Scan', 'Statut', 'IP'];
        $colLetters = range('A', 'J');

        // Style des en-têtes
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E40AF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        foreach ($colLetters as $i => $col) {
            $sheet->setCellValue($col . '1', $headers[$i]);
            $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Données
        $row = 2;
        foreach ($presences as $p) {
            $sheet->setCellValue('A' . $row, ($p->etudiant->prenom ?? '') . ' ' . ($p->etudiant->nom ?? ''));
            $sheet->setCellValue('B' . $row, $p->etudiant->prenom ?? '');
            $sheet->setCellValue('C' . $row, $p->etudiant->nom ?? '');
            $sheet->setCellValue('D' . $row, $p->etudiant->matricule ?? 'N/A');
            $sheet->setCellValue('E' . $row, $p->etudiant->filiere?->code ?? 'N/A');
            $sheet->setCellValue('F' . $row, $p->evenement->ec?->intitule ?? 'N/A');
            $sheet->setCellValue('G' . $row, $p->evenement->date?->format('Y-m-d') ?? 'N/A');
            $sheet->setCellValue('H' . $row, $p->heure_scan?->format('Y-m-d H:i:s') ?? 'N/A');

            $statutLabel = match ($p->statut) {
                'valide'  => 'Présent',
                'absent'  => 'Absent',
                'suspect' => 'Suspect',
                'en_retard' => 'En retard',
                default   => $p->statut,
            };
            $sheet->setCellValue('I' . $row, $statutLabel);
            $sheet->setCellValue('J' . $row, $p->ip_address ?? '');

            // Alternance de couleurs pour les lignes
            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':J' . $row)
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->setStartColor(new Color('FFF3F4F6'));
            }

            $row++;
        }

        // Ajuster la largeur des colonnes après avoir rempli
        foreach ($colLetters as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "historique_presences_{$dateLabel}.xlsx";

        $headers = [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename={$filename}",
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
