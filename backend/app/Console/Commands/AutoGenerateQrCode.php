<?php

namespace App\Console\Commands;

use App\Models\Evenement;
use App\Models\QrCode;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AutoGenerateQrCode extends Command
{
    protected $signature = 'qrcode:auto-generate';
    protected $description = 'Génère automatiquement les QR codes 15 min avant la fin des cours du jour — renouvelé toutes les 150s';

    public function handle(): int
    {
        // Vérifier le délai minimum entre deux régénérations (150 secondes)
        $lastRun = cache()->get('qrcode:auto-generate:last-run');
        if ($lastRun && now()->diffInSeconds($lastRun) < 150) {
            return Command::SUCCESS;
        }
        cache()->put('qrcode:auto-generate:last-run', now(), 600); // 10 min TTL

        $maintenant = now();
        $today = $maintenant->format('Y-m-d');

        // Trouver les événements aujourd'hui : 15 min avant heure_fin → maintenant
        $evenements = Evenement::where('date', $today)
            ->where('statut', 'planifie')
            ->get()
            ->filter(function ($event) use ($maintenant, $today) {
                // L'événement doit être dans sa phase finale : 15 min avant la fin
                $heureFin = Carbon::parse($today . ' ' . $event->heure_fin);
                $fenetreDebut = $heureFin->copy()->subMinutes(15);

                return $maintenant->between($fenetreDebut, $heureFin);
            });

        if ($evenements->isEmpty()) {
            $this->info("Aucun événement en phase de génération de QR code pour le moment.");
            return Command::SUCCESS;
        }

        $total = 0;

        foreach ($evenements as $evenement) {
            // Désactiver les anciens QR codes
            QrCode::where('evenement_id', $evenement->id)
                ->where('actif', true)
                ->update(['actif' => false]);

            // Durée de validité : jusqu'à la fin du cours (+ 5 min de grâce)
            $expireAt = Carbon::parse($today . ' ' . $evenement->heure_fin)->addMinutes(5);

            $token = (string) Str::uuid();

            QrCode::create([
                'evenement_id' => $evenement->id,
                'token'        => $token,
                'expire_at'    => $expireAt,
                'actif'        => true,
            ]);

            // Marquer l'événement comme "en_cours" si pas déjà fait
            if ($evenement->statut === 'planifie') {
                $evenement->update(['statut' => 'en_cours']);
            }

            $ecIntitule = $evenement->ec->intitule ?? 'N/A';
            $this->line("  QR généré : Événement #{$evenement->id} ({$ecIntitule}) expire à {$expireAt->format('H:i')}");
            $total++;
        }

        $this->info("QR codes générés : {$total}");

        return Command::SUCCESS;
    }
}
