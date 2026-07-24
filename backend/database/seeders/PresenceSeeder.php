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
        $stats = ['valide' => 0, 'rejete' => 0, 'suspect' => 0];
        $statuses = ['valide', 'valide', 'valide', 'valide', 'valide', 'valide', 'valide', 'suspect', 'suspect', 'rejete'];
        $chunks = [];
        $now = now();

        foreach ($evenements as $evenement) {
            // Récupérer les étudiants inscrits aux ECs de cet événement
            $etudiantIds = DB::table('etudiant_ec')
                ->where('ec_id', $evenement->ec_id)
                ->where('annee_id', $evenement->annee_id)
                ->pluck('etudiant_id');

            if ($etudiantIds->isEmpty()) continue;

            // Environ 30% des étudiants ont scanné (présence enregistrée)
            // Réduit de 80% à 30% pour alléger la DB
            $presentCount = (int) ceil($etudiantIds->count() * 0.3);
            $presentIds = $etudiantIds->random($presentCount);

            foreach ($presentIds as $etudiantId) {
                $heureScan = \Carbon\Carbon::parse($evenement->date->format('Y-m-d') . ' ' . $evenement->heure_debut)
                    ->addMinutes(mt_rand(-5, 20));

                $statut = $statuses[array_rand($statuses)];

                // Déterminer si c'est un retard (scan > 15 min après début)
                $heureDebut = \Carbon\Carbon::parse($evenement->date->format('Y-m-d') . ' ' . $evenement->heure_debut);
                $diffMinutes = $heureScan->diffInMinutes($heureDebut);
                $isRetard = $diffMinutes > 15;

                $chunks[] = [
                    'etudiant_id'       => $etudiantId,
                    'evenement_id'      => $evenement->id,
                    'heure_scan'        => $heureScan,
                    'device_fingerprint'=> 'seeder-device-' . mt_rand(1, 1000),
                    'ip_address'        => '192.168.' . mt_rand(0, 255) . '.' . mt_rand(1, 254),
                    'statut'            => $statut,
                    'latitude'          => 6.4 + mt_rand(-10, 10) / 1000,
                    'longitude'         => 2.3 + mt_rand(-10, 10) / 1000,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
                $total++;

                $stats[$statut]++;

                // Insérer par lots de 50
                if (count($chunks) >= 50) {
                    Presence::insert($chunks);
                    $chunks = [];
                }
            }
        }

        // Dernier lot
        if (!empty($chunks)) {
            Presence::insert($chunks);
        }

        $this->command->info("Présences créées : {$total}");
        $this->command->line("  - Valides : {$stats['valide']}");
        $this->command->line("  - Suspects : {$stats['suspect']}");
        $this->command->line("  - Rejetés : {$stats['rejete']}");
    }
}
