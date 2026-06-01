<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Presence;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
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
     * Export des présences au format CSV (CDC 15.2).
     *
     * GET /api/admin/reports/presence/{evenementId}/csv
     */
    public function exportCsv(Request $request, int $evenementId): mixed
    {
        $evenement = Evenement::with(['ec', 'presences.etudiant'])->findOrFail($evenementId);
        $presences = $evenement->presences;

        $filename = "presence_{$evenementId}_" . now()->format('Ymd_His') . ".csv";

        $headers = [
            'Content-Type'              => 'text/csv; charset=UTF-8',
            'Content-Disposition'       => "attachment; filename={$filename}",
            'Pragma'                    => 'no-cache',
            'Cache-Control'             => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'                   => '0',
        ];

        $callback = function () use ($presences) {
            $output = fopen('php://output', 'w');

            // BOM UTF-8 pour Excel
            fputs($output, "\xEF\xBB\xBF");

            fputcsv($output, ['Nom', 'Prénom', 'Matricule', 'Email', 'Heure Scan', 'Statut', 'Device ID', 'IP']);

            foreach ($presences as $p) {
                fputcsv($output, [
                    $p->etudiant->nom ?? 'N/A',
                    $p->etudiant->prenom ?? 'N/A',
                    $p->etudiant->matricule ?? 'N/A',
                    $p->etudiant->email ?? 'N/A',
                    $p->heure_scan ? $p->heure_scan->format('d/m/Y H:i:s') : 'N/A',
                    $p->statut,
                    $p->device_fingerprint ?? '',
                    $p->ip_address ?? '',
                ]);
            }

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
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
     * GET /api/admin/reports/semester/{anneeAcademique}
     */
    public function semesterReport(AnneeAcademique $anneeAcademique): JsonResponse
    {
        $totalEtudiants = Etudiant::where('annee_id', $anneeAcademique->id)->count();
        $totalEvenements = Evenement::where('annee_id', $anneeAcademique->id)
            ->where('date', '<', now())
            ->count();

        $presences = Presence::whereHas('etudiant', fn($q) => $q->where('annee_id', $anneeAcademique->id))
            ->whereHas('evenement', fn($q) => $q->where('annee_id', $anneeAcademique->id))
            ->count();

        $taux = $totalEvenements > 0 && $totalEtudiants > 0
            ? round(($presences / ($totalEvenements * $totalEtudiants)) * 100, 1)
            : 0;

        $statsParFiliere = Filiere::select('filieres.code', 'filieres.intitule', DB::raw('COUNT(presences.id) as total_presences'))
            ->join('etudiants', 'etudiants.filiere_id', '=', 'filieres.id')
            ->leftJoin('presences', 'etudiants.id', '=', 'presences.etudiant_id')
            ->where('etudiants.annee_id', $anneeAcademique->id)
            ->groupBy('filieres.id', 'filieres.code', 'filieres.intitule')
            ->get();

        return $this->successResponse([
            'annee_academique'   => ['id' => $anneeAcademique->id, 'annee' => $anneeAcademique->annee],
            'total_etudiants'    => $totalEtudiants,
            'total_evenements'   => $totalEvenements,
            'total_presences'    => $presences,
            'taux_presence'      => $taux,
            'stats_par_filiere'  => $statsParFiliere,
        ]);
    }

    /**
     * Export Excel des données de présence.
     * GET /api/admin/reports/excel/export
     */
    public function excelExport(Request $request): mixed
    {
        $query = Presence::with(['etudiant.filiere', 'evenement.ec']);

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
