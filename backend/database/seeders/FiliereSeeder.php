<?php

namespace Database\Seeders;

use App\Models\Etablissement;
use App\Models\Filiere;
use Illuminate\Database\Seeder;

class FiliereSeeder extends Seeder
{
    public function run(): void
    {
        $ifri = Etablissement::where('code', 'IFRI')->first();
        $etablissementId = $ifri?->id;

        $filieres = [
            // À l'IFRI, « IM » est la licence Internet et Multimédia.
            ['code' => 'IM-L1',    'intitule' => 'Internet et Multimédia (L1)',                         'niveau' => 'L1'],
            ['code' => 'IM-L2',    'intitule' => 'Internet et Multimédia (L2)',                         'niveau' => 'L2'],
            ['code' => 'IM-L3',    'intitule' => 'Internet et Multimédia (L3)',                         'niveau' => 'L3'],
            ['code' => 'GL-M1',    'intitule' => 'Génie Logiciel (M1)',                                'niveau' => 'M1'],
            ['code' => 'GL-M2',    'intitule' => 'Génie Logiciel (M2)',                                'niveau' => 'M2'],
        ];

        // Filières de démonstration : MIAGE, RIT et GEA ne sont pas des
        // formations de l'IFRI. Elles ne servent qu'aux seeders de développement
        // et de test (UeSeeder, EcSeeder, EtudiantSeeder les cherchent par code) ;
        // en production, start.sh rejoue ce seeder à chaque démarrage et les
        // recréerait après leur suppression.
        if (! app()->environment('production')) {
            $filieres = array_merge($filieres, [
                ['code' => 'MIAGE-M1', 'intitule' => 'Mathématiques, Informatique et Gestion (M1)',     'niveau' => 'M1'],
                ['code' => 'MIAGE-M2', 'intitule' => 'Mathématiques, Informatique et Gestion (M2)',     'niveau' => 'M2'],
                ['code' => 'RIT-L3',   'intitule' => 'Réseaux et Informatique Télécom (L3)',            'niveau' => 'L3'],
                ['code' => 'GEA-L1',   'intitule' => 'Gestion des Entreprises et Administrations (L1)', 'niveau' => 'L1'],
                ['code' => 'GEA-L2',   'intitule' => 'Gestion des Entreprises et Administrations (L2)', 'niveau' => 'L2'],
            ]);
        }

        foreach ($filieres as $filiere) {
            $filiere['etablissement_id'] = $etablissementId;
            Filiere::updateOrCreate(
                ['code' => $filiere['code']],
                $filiere
            );
        }

        $this->command->info('Filières créées : ' . count($filieres));
    }
}
