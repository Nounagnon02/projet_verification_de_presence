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
        $chunks = [];

        foreach ($ecs as $ec) {
            $filiere = $ec->ue->filiere;

            // Créer 2 événements par EC : 1 passé (avril) + 1 à venir (juin)
            // Réduit pour éviter les timeouts et la surcharge de données

            // Session passée (avril 2026)
            $jourPasse = mt_rand(8, 25);
            $creneau = $heures[array_rand($heures)];
            $date = \Carbon\Carbon::create(2026, 4, $jourPasse);
            if (!$date->isWeekend()) {
                $chunks[] = [
                    'ec_id'       => $ec->id,
                    'filiere_id'  => $filiere->id,
                    'annee_id'    => $activeAnnee,
                    'date'        => $date->format('Y-m-d'),
                    'heure_debut' => $creneau['debut'],
                    'heure_fin'   => $creneau['fin'],
                    'salle'       => $salles[array_rand($salles)],
                    'statut'      => mt_rand(0, 2) ? 'termine' : 'annule',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
                $total++;
            }

            // Session à venir (juin 2026)
            $jourFutur = mt_rand(10, 25);
            $creneau2 = $heures[array_rand($heures)];
            $date2 = \Carbon\Carbon::create(2026, 6, $jourFutur);
            if (!$date2->isWeekend() && $date2->greaterThan($now->subDay())) {
                $chunks[] = [
                    'ec_id'       => $ec->id,
                    'filiere_id'  => $filiere->id,
                    'annee_id'    => $activeAnnee,
                    'date'        => $date2->format('Y-m-d'),
                    'heure_debut' => $creneau2['debut'],
                    'heure_fin'   => $creneau2['fin'],
                    'salle'       => $salles[array_rand($salles)],
                    'statut'      => 'planifie',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
                $total++;
            }

            // Insérer par lots de 50
            if (count($chunks) >= 50) {
                Evenement::insert($chunks);
                $chunks = [];
            }
        }

        // Dernier lot
        if (!empty($chunks)) {
            Evenement::insert($chunks);
        }

        $this->command->info("Événements créés : {$total}");
    }
}
