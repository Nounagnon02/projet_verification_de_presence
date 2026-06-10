<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use App\Models\Etablissement;
use Illuminate\Database\Seeder;

class AnneeAcademiqueSeeder extends Seeder
{
    public function run(): void
    {
        $ifri = Etablissement::where('code', 'IFRI')->first();
        $etablissementId = $ifri?->id;

        $annees = [
            ['libelle' => '2023-2024', 'date_debut' => '2023-10-01', 'date_fin' => '2024-09-30', 'active' => false],
            ['libelle' => '2024-2025', 'date_debut' => '2024-10-01', 'date_fin' => '2025-09-30', 'active' => false],
            ['libelle' => '2025-2026', 'date_debut' => '2025-10-01', 'date_fin' => '2026-09-30', 'active' => true],
        ];

        foreach ($annees as $annee) {
            $annee['etablissement_id'] = $etablissementId;
            AnneeAcademique::updateOrCreate(
                ['libelle' => $annee['libelle']],
                $annee
            );
        }

        $this->command->info('Années académiques créées : ' . count($annees));
    }
}
