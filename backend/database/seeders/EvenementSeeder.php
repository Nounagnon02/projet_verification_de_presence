<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Evenement;
use Illuminate\Database\Seeder;

class EvenementSeeder extends Seeder
{
    public function run(): void
    {
        $activeAnnee = AnneeAcademique::where('active', true)->first()->id;
        $ecs = Ec::with('ue.filiere')->get();

        if ($ecs->isEmpty()) {
            $this->command->warn('Aucun EC trouvé. Exécute d\'abord EcSeeder.');
            return;
        }

        $salles = ['Salle 101', 'Salle 102', 'Salle 103', 'Amphi A', 'Amphi B',
                   'Labo Info 1', 'Labo Info 2', 'Salle TP 1', 'Salle TP 2',
                   'Salle 201', 'Salle 202', 'Amphi C', 'Salle 301'];

        $heures = [
            ['debut' => '08:00', 'fin' => '10:00'],
            ['debut' => '10:15', 'fin' => '12:15'],
            ['debut' => '13:00', 'fin' => '15:00'],
            ['debut' => '15:15', 'fin' => '17:15'],
        ];

        $total = 0;
        $now = now();

        foreach ($ecs as $ec) {
            $filiere = $ec->ue->filiere;

            // Créer 8 événements par EC : 6 passés (de février à mai) + 2 à venir (juin)
            $sessionCount = 0;

            // Sessions passées (février - mai 2026)
            for ($month = 2; $month <= 5; $month++) {
                $day = min(mt_rand(5, 28), $month === 2 ? 25 : 28);
                $creneau = $heures[array_rand($heures)];

                $date = \Carbon\Carbon::create(2026, $month, $day);
                if ($date->isWeekend()) continue; // Skip weekends

                $statut = match (true) {
                    $month <= 3 => 'termine',
                    $month == 4 => mt_rand(0, 2) ? 'termine' : 'annule',
                    $month == 5 => mt_rand(0, 3) ? 'termine' : 'annule',
                    default => 'termine',
                };

                $salle = $salles[array_rand($salles)];

                Evenement::create([
                    'ec_id'       => $ec->id,
                    'filiere_id'  => $filiere->id,
                    'annee_id'    => $activeAnnee,
                    'date'        => $date->format('Y-m-d'),
                    'heure_debut' => $creneau['debut'],
                    'heure_fin'   => $creneau['fin'],
                    'salle'       => $salle,
                    'statut'      => $statut,
                ]);
                $total++;
                $sessionCount++;
            }

            // Sessions à venir (juin 2026)
            for ($i = 0; $i < 2; $i++) {
                $day = mt_rand(10, 25);
                $creneau = $heures[array_rand($heures)];

                $date = \Carbon\Carbon::create(2026, 6, $day);
                if ($date->isWeekend() || $date->lessThan($now->subDay())) continue;

                $statut = 'planifie';

                Evenement::create([
                    'ec_id'       => $ec->id,
                    'filiere_id'  => $filiere->id,
                    'annee_id'    => $activeAnnee,
                    'date'        => $date->format('Y-m-d'),
                    'heure_debut' => $creneau['debut'],
                    'heure_fin'   => $creneau['fin'],
                    'salle'       => $salles[array_rand($salles)],
                    'statut'      => $statut,
                ]);
                $total++;
            }
        }

        $this->command->info("Événements créés : {$total}");
    }
}
