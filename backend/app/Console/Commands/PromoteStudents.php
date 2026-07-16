<?php

namespace App\Console\Commands;

use App\Models\Etudiant;
use App\Models\Filiere;
use Illuminate\Console\Command;

class PromoteStudents extends Command
{
    /**
     * Promouvoir les étudiants d'une filière à une autre (ex: IM-L1 → IM-L2).
     *
     * Usage :
     *   php artisan students:promote IM-L1 IM-L2                        # Change la filière
     *   php artisan students:promote IM-L1 IM-L2 --toAnnee=2026-2027    # Change filière + année
     *   php artisan students:promote IM-L1 IM-L2 --dry-run              # Simuler sans modifier
     */
    protected $signature = 'students:promote
                            {fromFiliere : Code de la filière source (ex: IM-L1)}
                            {toFiliere : Code de la filière destination (ex: IM-L2)}
                            {--toAnnee= : Libellé de l\'année académique destination (ex: 2026-2027)}
                            {--dry-run : Simuler la promotion sans modifier les données}';

    protected $description = 'Promouvoir les étudiants d\'une filière à une autre avec recalcul des inscriptions aux ECs';

    public function handle(): int
    {
        $fromCode = $this->argument('fromFiliere');
        $toCode   = $this->argument('toFiliere');
        $dryRun   = $this->option('dry-run');

        // Résoudre les filières
        $fromFiliere = Filiere::where('code', $fromCode)->first();
        $toFiliere   = Filiere::where('code', $toCode)->first();

        if (!$fromFiliere) {
            $this->error("Filière source « {$fromCode} » introuvable.");
            return Command::FAILURE;
        }

        if (!$toFiliere) {
            $this->error("Filière destination « {$toCode} » introuvable.");
            return Command::FAILURE;
        }

        // Résoudre l'année destination si fournie
        $toAnneeId = null;
        if ($toAnnee = $this->option('toAnnee')) {
            $anneeModel = \App\Models\AnneeAcademique::where('libelle', $toAnnee)->first();
            if (!$anneeModel) {
                $this->error("Année académique « {$toAnnee} » introuvable.");
                return Command::FAILURE;
            }
            $toAnneeId = $anneeModel->id;
        }

        // Récupérer les étudiants de la filière source
        $query = Etudiant::where('filiere_id', $fromFiliere->id);
        $count = $query->count();

        if ($count === 0) {
            $this->warn("Aucun étudiant trouvé dans la filière « {$fromCode} ».");
            return Command::SUCCESS;
        }

        $this->info("Filière source      : {$fromCode} (ID: {$fromFiliere->id}) — {$count} étudiant(s)");
        $this->info("Filière destination : {$toCode} (ID: {$toFiliere->id})");
        if ($toAnneeId) {
            $this->info("Année destination   : {$toAnnee} (ID: {$toAnneeId})");
        }
        $this->newLine();

        if ($dryRun) {
            $this->warn("=== MODE SIMULATION (--dry-run) ===");
            $this->newLine();

            $students = $query->get(['id', 'nom', 'prenom', 'matricule']);
            $this->table(
                ['ID', 'Nom', 'Prénom', 'Matricule', 'Nouvelle Filière', 'Nouvelle Année'],
                $students->map(fn($s) => [
                    $s->id,
                    $s->nom,
                    $s->prenom,
                    $s->matricule,
                    $toCode,
                    $toAnnee ?? '(inchangée)',
                ])->toArray()
            );

            $this->newLine();
            $this->info("Simulation terminée. {$count} étudiant(s) auraient été promu(s).");
            return Command::SUCCESS;
        }

        // Exécuter la promotion
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $promoted = 0;
        $query->chunkById(50, function ($students) use ($toFiliere, $toAnneeId, $bar, &$promoted) {
            foreach ($students as $student) {
                $student->filiere_id = $toFiliere->id;

                if ($toAnneeId) {
                    $student->annee_id = $toAnneeId;
                }

                $student->save();

                // Recalculer les inscriptions aux ECs
                $student->recalculateEnrollments();

                $promoted++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ {$promoted} étudiant(s) promu(s) avec succès de « {$fromCode} » vers « {$toCode} ».");

        return Command::SUCCESS;
    }
}
