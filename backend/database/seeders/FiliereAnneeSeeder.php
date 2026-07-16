<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use App\Models\Filiere;
use Illuminate\Database\Seeder;

class FiliereAnneeSeeder extends Seeder
{
    /**
     * Lie les filières existantes à l'année académique active.
     * Chaque filière se voit rattacher l'année active par défaut.
     */
    public function run(): void
    {
        $activeAnnee = AnneeAcademique::where('active', true)->first();

        if (!$activeAnnee) {
            $this->command->warn('Aucune année académique active trouvée.');

            // Fallback : prendre la première année disponible
            $activeAnnee = AnneeAcademique::first();
            if (!$activeAnnee) {
                $this->command->error('Aucune année académique trouvée dans la base.');
                return;
            }
        }

        $filieres = Filiere::all();
        $count = 0;

        foreach ($filieres as $filiere) {
            // Éviter les doublons (insertOrIgnore)
            $filiere->anneesAcademiques()->syncWithoutDetaching([$activeAnnee->id]);
            $count++;
        }

        $this->command->info("Filières liées à l'année « {$activeAnnee->libelle} » : {$count}");
    }
}
