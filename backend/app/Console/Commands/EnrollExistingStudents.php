<?php

namespace App\Console\Commands;

use App\Models\Ec;
use App\Models\Etudiant;
use Illuminate\Console\Command;

class EnrollExistingStudents extends Command
{
    /**
     * Inscrit tous les étudiants existants aux ECs de leur filière et année.
     * Conforme CDC 7.2.3 — Association automatique cours-étudiant.
     *
     * À exécuter une seule fois après la migration de la table etudiant_ec.
     * Les nouveaux étudiants seront inscrits automatiquement à la création.
     *
     * Usage : php artisan app:enroll-existing-students
     */
    protected $signature = 'app:enroll-existing-students {--dry-run : Affiche les inscriptions sans les créer}';
    protected $description = 'Inscrit tous les étudiants existants aux ECs de leur filière et année';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $totalEnrolled = 0;
        $studentsWithEcs = 0;

        $etudiants = Etudiant::all();
        $bar = $this->output->createProgressBar($etudiants->count());
        $bar->start();

        foreach ($etudiants as $etudiant) {
            $ecs = Ec::forFiliereAndYear($etudiant->filiere_id, $etudiant->annee_id);
            $count = 0;

            foreach ($ecs as $ec) {
                if ($dryRun) {
                    $count++;
                    continue;
                }

                try {
                    $etudiant->ecs()->syncWithoutDetaching([
                        $ec->id => ['annee_id' => $etudiant->annee_id],
                    ]);
                    $count++;
                } catch (\Exception $e) {
                    $this->warn("  Erreur {$etudiant->nom} → EC #{$ec->id} : {$e->getMessage()}");
                }
            }

            $totalEnrolled += $count;
            if ($count > 0) $studentsWithEcs++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($dryRun) {
            $this->info("Simulation : {$studentsWithEcs} étudiant(s) auraient reçu {$totalEnrolled} inscription(s).");
        } else {
            $this->info("Terminé : {$studentsWithEcs} étudiant(s) inscrits — {$totalEnrolled} inscription(s) créée(s).");
        }

        return Command::SUCCESS;
    }
}
