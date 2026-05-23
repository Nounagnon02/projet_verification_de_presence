<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
