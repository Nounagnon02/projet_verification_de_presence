<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Ordre d'exécution respectant les dépendances FK :
     * 1. Annees & Filieres (aucune dépendance)
     * 2. AdminUser
     * 3. Ues (dépend de filieres, annees)
     * 4. Ecs (dépend de ues)
     * 5. Etudiants (dépend de filieres, annees)
     * 6. EtudiantEc (dépend de etudiants, ecs, annees)
     * 7. Evenements (dépend de ecs, filieres, annees)
     * 8. Presences (dépend de etudiants, evenements)
     * 9. QrCodes (dépend de evenements)
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,   // IFRI créé en premier
            AnneeAcademiqueSeeder::class,
            FiliereSeeder::class,
            UeSeeder::class,
            EcSeeder::class,
            EtudiantSeeder::class,
            EtudiantEcSeeder::class,
            EvenementSeeder::class,
            PresenceSeeder::class,
            QrCodeSeeder::class,
        ]);

        $this->command->info('═══════════════════════════════════════');
        $this->command->info('   Base de données peuplée avec succès !');
        $this->command->info('═══════════════════════════════════════');
    }
}
