<?php

namespace Database\Seeders;

use App\Models\Evenement;
use App\Models\Presence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PresenceSeeder extends Seeder
{
    public function run(): void
    {
        // Récupérer les événements passés (statut = 'termine')
        $evenements = Evenement::whereIn('statut', ['termine', 'en_cours'])
            ->with('ec.ue.filiere')
            ->get();

        if ($evenements->isEmpty()) {
            $this->command->warn('Aucun événement passé trouvé. Exécute d\'abord EvenementSeeder.');
            return;
        }

        $total = 0;
        $stats = ['present' => 0, 'absent' => 0, 'retard' => 0];
        $statuses = ['present', 'present', 'present', 'present', 'present', 'present', 'present', 'retard', 'absent'];

        foreach ($evenements as $evenement) {
            // Récupérer les étudiants inscrits aux ECs de cet événement
            $etudiantIds = DB::table('etudiant_ec')
                ->where('ec_id', $evenement->ec_id)
                ->where('annee_id', $evenement->annee_id)
                ->pluck('etudiant_id');

            if ($etudiantIds->isEmpty()) continue;

            // Environ 80% des étudiants ont scanné (présence enregistrée)
            $presentCount = (int) ceil($etudiantIds->count() * 0.8);
            $presentIds = $etudiantIds->random($presentCount);

            foreach ($presentIds as $etudiantId) {
                $heureScan = \Carbon\Carbon::parse($evenement->date->format('Y-m-d') . ' ' . $evenement->heure_debut)
                    ->addMinutes(mt_rand(-5, 20));

                $statut = $statuses[array_rand($statuses)];

                // Déterminer si c'est un retard (scan > 15 min après début)
                $heureDebut = \Carbon\Carbon::parse($evenement->date->format('Y-m-d') . ' ' . $evenement->heure_debut);
                $diffMinutes = $heureScan->diffInMinutes($heureDebut);
                $isRetard = $diffMinutes > 15;

                Presence::firstOrCreate(
                    ['etudiant_id' => $etudiantId, 'evenement_id' => $evenement->id],
                    [
                    'heure_scan'         => $heureScan,
                    'device_fingerprint' => 'seeder-device-' . mt_rand(1, 1000),
                    'ip_address'         => '192.168.' . mt_rand(0, 255) . '.' . mt_rand(1, 254),
                    'statut'             => $statut,
                    'latitude'           => 6.4 + mt_rand(-10, 10) / 1000,
                    'longitude'          => 2.3 + mt_rand(-10, 10) / 1000,
                ]);
                $total++;

                $stats[$isRetard ? 'retard' : 'present']++;
            }
        }

        $this->command->info("Présences créées : {$total}");
        $this->command->line("  - Présents : {$stats['present']}");
        $this->command->line("  - Retards : {$stats['retard']}");
    }
}
