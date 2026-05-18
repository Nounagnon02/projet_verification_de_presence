<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Models\Presence;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Response;

class ReportController extends Controller
{
    /**
     * Export des présences en PDF (CDC 15.2 & 16)
     */
    public function exportPdf(Request $request, $evenementId)
    {
        $evenement = Evenement::with(['ec', 'presences.etudiant'])->findOrFail($evenementId);
        
        $data = [
            'evenement' => $evenement,
            'date' => now()->format('d/m/Y H:i'),
            'title' => 'Rapport de Présence - ' . $evenement->ec->intitule
        ];

        $pdf = Pdf::loadView('reports.presence', $data);
        
        return $pdf->download("presence_{$evenementId}.pdf");
    }

    /**
     * Export des présences en CSV (CDC 15.2)
     */
    public function exportCsv(Request $request, $evenementId)
    {
        $evenement = Evenement::with(['ec', 'presences.etudiant'])->findOrFail($evenementId);
        $presences = $evenement->presences;

        $filename = "presence_{$evenementId}.csv";
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['Nom', 'Prénom', 'Matricule', 'Heure Scan', 'Statut', 'Device ID'];

        $callback = function() use($presences, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($presences as $p) {
                fputcsv($file, [
                    $p->etudiant->nom,
                    $p->etudiant->prenom,
                    $p->etudiant->matricule,
                    $p->heure_scan->format('H:i:s'),
                    $p->statut,
                    $p->device_fingerprint
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
