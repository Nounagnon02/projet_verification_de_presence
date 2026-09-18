<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Evenement;
use App\Models\QrCode;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AutoGenerateQrCode extends Command
{
    protected $signature = 'qrcode:auto-generate';
    protected $description = 'Génère et fait tourner les QR codes des cours dont la fenêtre de présence est ouverte';

    public function handle(): int
    {
        $maintenant = now();

        // Les événements de la veille restent candidats : une séance à cheval sur
        // minuit garde sa fenêtre ouverte après le changement de date.
        $evenements = Evenement::whereIn('statut', ['planifie', 'en_cours'])
            ->whereBetween('date', [
                $maintenant->copy()->subDay()->toDateString(),
                $maintenant->toDateString(),
            ])
            ->with('ec')
            ->get()
            ->filter(fn (Evenement $evenement) => $maintenant->betweenIncluded(
                $evenement->ouvertureScan(),
                $evenement->fermetureScan()
            ));

        if ($evenements->isEmpty()) {
            $this->info('Aucun cours dont la fenêtre de présence est ouverte.');

            return Command::SUCCESS;
        }

        $total = 0;

        foreach ($evenements as $evenement) {
            // Rotation à chaque passage, sans garde-fou de durée. Le planificateur
            // tourne chaque minute : c'est lui qui fixe la cadence réelle, et un
            // garde-fou exprimé en secondes ne faisait que la doubler dès qu'un
            // passage tombait quelques secondes trop tôt.
            QrCode::where('evenement_id', $evenement->id)
                ->where('actif', true)
                ->update(['actif' => false]);

            $expireAt = $evenement->expirationTokenDepuis($maintenant);

            QrCode::create([
                'evenement_id' => $evenement->id,
                'token'        => (string) Str::uuid(),
                'expire_at'    => $expireAt,
                'actif'        => true,
            ]);

            // Le filtre de sélection inclut « en_cours » : sans cela, l'événement
            // quittait la sélection dès ce passage à l'état suivant et son token
            // n'était plus jamais renouvelé — le QR Code restait le même pendant
            // toute la séance, ce qui annulait la protection anti-partage.
            if ($evenement->statut === 'planifie') {
                $evenement->update(['statut' => 'en_cours']);
            }

            $this->line(sprintf(
                '  QR renouvelé : événement #%d (%s), valable jusqu\'à %s',
                $evenement->id,
                $evenement->ec->intitule ?? 'N/A',
                $expireAt->format('H:i:s')
            ));
            $total++;
        }

        $this->info("QR codes renouvelés : {$total}");

        return Command::SUCCESS;
    }
}
