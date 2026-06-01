<?php

namespace Database\Seeders;

use App\Models\Filiere;
use Illuminate\Database\Seeder;

class FiliereSeeder extends Seeder
{
    public function run(): void
    {
        $filieres = [
            ['code' => 'IM-L1',    'intitule' => 'Informatique et Mathématiques (L1)',                  'niveau' => 'L1'],
            ['code' => 'IM-L2',    'intitule' => 'Informatique et Mathématiques (L2)',                  'niveau' => 'L2'],
            ['code' => 'IM-L3',    'intitule' => 'Informatique et Mathématiques (L3)',                  'niveau' => 'L3'],
            ['code' => 'MIAGE-M1', 'intitule' => 'Mathématiques, Informatique et Gestion (M1)',        'niveau' => 'M1'],
            ['code' => 'MIAGE-M2', 'intitule' => 'Mathématiques, Informatique et Gestion (M2)',        'niveau' => 'M2'],
            ['code' => 'GL-M1',    'intitule' => 'Génie Logiciel (M1)',                                'niveau' => 'M1'],
            ['code' => 'GL-M2',    'intitule' => 'Génie Logiciel (M2)',                                'niveau' => 'M2'],
            ['code' => 'RIT-L3',   'intitule' => 'Réseaux et Informatique Télécom (L3)',               'niveau' => 'L3'],
            ['code' => 'GEA-L1',   'intitule' => 'Gestion des Entreprises et Administrations (L1)',    'niveau' => 'L1'],
            ['code' => 'GEA-L2',   'intitule' => 'Gestion des Entreprises et Administrations (L2)',    'niveau' => 'L2'],
        ];

        foreach ($filieres as $filiere) {
            Filiere::firstOrCreate(['code' => $filiere['code']], $filiere);
        }

        $this->command->info('Filières créées : ' . count($filieres));
    }
}
