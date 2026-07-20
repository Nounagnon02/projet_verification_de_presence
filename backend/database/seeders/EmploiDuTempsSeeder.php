<?php

namespace Database\Seeders;

use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Filiere;
use Illuminate\Database\Seeder;

class EmploiDuTempsSeeder extends Seeder
{
    /**
     * Crée un emploi du temps hebdomadaire à partir des ECs existants.
     * Chaque EC reçoit 1 à 3 créneaux par semaine selon son volume horaire.
     */
    public function run(): void
    {
        $ecs = Ec::with('ue.filiere')->get();

        if ($ecs->isEmpty()) {
            $this->command->warn('Aucun EC trouvé. Exécute d\'abord EcSeeder.');
            return;
        }

        $activeAnnee = \App\Models\AnneeAcademique::where('active', true)->first();
        if (!$activeAnnee) {
            $this->command->warn('Aucune année académique active trouvée.');
            return;
        }

        $creneaux = [
            ['debut' => '08:00', 'fin' => '10:00'],
            ['debut' => '10:15', 'fin' => '12:15'],
            ['debut' => '13:00', 'fin' => '15:00'],
            ['debut' => '15:15', 'fin' => '17:15'],
        ];

        $salles = ['Salle 101', 'Salle 102', 'Salle 103', 'Amphi A', 'Amphi B',
                   'Labo Info 1', 'Labo Info 2', 'Salle TP 1'];

        $total = 0;

        foreach ($ecs as $ec) {
            $filiere = $ec->ue->filiere;
            $nbCreneaux = min(3, max(1, (int) ceil($ec->volume_horaire / 20)));

            $joursUtilises = [];

            for ($i = 0; $i < $nbCreneaux; $i++) {
                // Éviter 2 créneaux le même jour pour le même EC
                $jour = rand(1, 5); // Lundi à Vendredi
                $tentatives = 0;
                while (in_array($jour, $joursUtilises) && $tentatives < 5) {
                    $jour = rand(1, 5);
                    $tentatives++;
                }
                $joursUtilises[] = $jour;

                $creneau = $creneaux[array_rand($creneaux)];

                EmploiDuTemps::create([
                    'ec_id'        => $ec->id,
                    'filiere_id'   => $filiere->id,
                    'annee_id'     => $activeAnnee->id,
                    'jour_semaine' => $jour,
                    'heure_debut'  => $creneau['debut'],
                    'heure_fin'    => $creneau['fin'],
                    'salle_libelle' => $salles[array_rand($salles)],
                    'type_cours'   => $i === 0 ? 'cours' : (rand(0, 1) ? 'td' : 'tp'),
                ]);

                $total++;
            }
        }

        $this->command->info("Emploi du temps créé : {$total} créneaux.");
    }
}
