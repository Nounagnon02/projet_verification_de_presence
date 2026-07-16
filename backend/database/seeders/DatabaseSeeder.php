<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Ordre d'exécution respectant les dépendances FK :
     * 1. Annees & Filieres (aucune dépendance)
     * 2. AdminUser
     * 3. FiliereAnnee (dépend de filieres, annees)
     * 4. Ues (dépend de filieres, annees)
     * 5. Ecs (dépend de ues)
     * 6. Etudiants (dépend de filieres, annees)
     * 7. EtudiantEc (dépend de etudiants, ecs, annees)
     * 8. Evenements (dépend de ecs, filieres, annees)
     * 9. Presences (dépend de etudiants, evenements)
     * 10. QrCodes (dépend de evenements)
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,   // IFRI créé en premier
            AnneeAcademiqueSeeder::class,
            FiliereSeeder::class,
            FiliereAnneeSeeder::class, // Lien filières ↔ années académiques
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
