<?php

namespace App\Console\Commands;

use App\Models\Ec;
use App\Models\Ue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncEcStatus extends Command
{
    protected $signature = 'ecs:sync-statut
                            {--ec-id= : Synchroniser un EC spécifique (optionnel)}
                            {--ue-id= : Synchroniser une UE spécifique et ses ECs (optionnel)}
                            {--dry-run : Afficher les changements sans les appliquer}';

    protected $description = 'Calcule le statut des ECs et UEs selon les heures de cours effectuées vs volume_horaire';

    public function handle(): int
    {
        $ecId = $this->option('ec-id');
        $ueId = $this->option('ue-id');
        $dryRun = $this->option('dry-run');

        $query = Ec::with('evenements');
        if ($ecId) {
            $query->where('id', $ecId);
        } elseif ($ueId) {
            $query->where('ue_id', $ueId);
        }

        $ecs = $query->get();
        if ($ecs->isEmpty()) {
            $this->warn('Aucun EC trouvé.');
            return Command::SUCCESS;
        }

        $stats = ['termine' => 0, 'en_cours' => 0, 'non_demarre' => 0, 'inchanges' => 0];

        foreach ($ecs as $ec) {
            // Calculer les heures effectuées : somme des durées des événements terminés
            $heuresEffectuees = (float) $ec->evenements()
                ->where('statut', 'termine')
                ->select(DB::raw("COALESCE(SUM(EXTRACT(EPOCH FROM (heure_fin - heure_debut)) / 3600), 0) as total"))
                ->value('total');

            // Déterminer le nouveau statut
            $nouveauStatut = match (true) {
                $heuresEffectuees <= 0                              => 'non_demarre',
                $heuresEffectuees < $ec->volume_horaire             => 'en_cours',
                default                                             => 'termine',
            };

            if ($nouveauStatut === $ec->statut) {
                $stats['inchanges']++;
                continue;
            }

            $oldStatut = $ec->statut;
            if (!$dryRun) {
                $ec->update(['statut' => $nouveauStatut]);
            }

            $this->line(sprintf(
                '  %s → %s : EC %s (%s) — %.1fh / %.1fh',
                str_pad($oldStatut, 12),
                str_pad($nouveauStatut, 12),
                $ec->code,
                $ec->intitule,
                $heuresEffectuees,
                $ec->volume_horaire
            ));
            $stats[$nouveauStatut]++;
        }

        // Synchroniser les statuts des UEs parentes
        $ueIds = $ecs->pluck('ue_id')->unique();
        foreach ($ueIds as $uid) {
            $this->syncUeStatut($uid, $dryRun);
        }

        // Résumé
        $this->newLine();
        $this->info('Synchronisation terminée.');
        $this->line("  Terminé   : {$stats['termine']}");
        $this->line("  En cours  : {$stats['en_cours']}");
        $this->line("  Non démarré : {$stats['non_demarre']}");
        if ($stats['inchanges'] > 0) {
            $this->line("  Inchangés : {$stats['inchanges']}");
        }
        if ($dryRun) {
            $this->warn('Mode dry-run : aucune modification appliquée.');
        }

        return Command::SUCCESS;
    }

    /**
     * Calcule le statut d'une UE à partir des statuts de ses ECs.
     */
    private function syncUeStatut(int $ueId, bool $dryRun = false): void
    {
        $ue = Ue::find($ueId);
        if (!$ue) return;

        $ecStatuts = Ec::where('ue_id', $ueId)->pluck('statut')->unique();

        $nouveauStatut = match (true) {
            $ecStatuts->every(fn($s) => $s === 'termine')    => 'termine',
            $ecStatuts->contains('en_cours')                  => 'en_cours',
            $ecStatuts->contains('termine')                   => 'en_cours',  // mix non_demarre + termine
            default                                           => 'non_demarre',
        };

        if ($nouveauStatut !== $ue->statut) {
            $old = $ue->statut;
            if (!$dryRun) {
                $ue->update(['statut' => $nouveauStatut]);
            }
            $this->line("  UE {$ue->code} : {$old} → {$nouveauStatut}");
        }
    }
}
