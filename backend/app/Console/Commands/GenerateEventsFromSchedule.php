<?php

namespace App\Console\Commands;

use App\Models\EmploiDuTemps;
use App\Models\Evenement;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateEventsFromSchedule extends Command
{
    protected $signature = 'events:generate-from-schedule
                            {--date= : Date cible (par défaut : aujourd\'hui)}
                            {--days=7 : Nombre de jours à générer}
                            {--force : Générer même pour les dates passées}';

    protected $description = 'Génère les événements (cours) depuis l\'emploi du temps pour les dates à venir';

    public function handle(): int
    {
        $dateStr = $this->option('date') ?? now()->format('Y-m-d');
        $days = (int) $this->option('days');
        $force = $this->option('force');

        $startDate = Carbon::parse($dateStr);
        $endDate = $startDate->copy()->addDays($days - 1);

        if (!$force && $startDate->isPast()) {
            $this->warn("La date {$dateStr} est dans le passé. Utilise --force pour générer quand même.");
            return Command::FAILURE;
        }

        $creneaux = EmploiDuTemps::with(['ec', 'filiere'])->get();

        if ($creneaux->isEmpty()) {
            $this->warn("Aucun créneau dans l'emploi du temps. Exécute d'abord db:seed --class=EmploiDuTempsSeeder");
            return Command::FAILURE;
        }

        $total = 0;
        $ignored = 0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $jourSemaine = $date->dayOfWeekIso;

            $creneauxDuJour = $creneaux->where('jour_semaine', $jourSemaine);

            foreach ($creneauxDuJour as $creneau) {
                $existe = Evenement::where('ec_id', $creneau->ec_id)
                    ->where('date', $date->format('Y-m-d'))
                    ->where('heure_debut', $creneau->heure_debut)
                    ->exists();

                if ($existe) {
                    $ignored++;
                    continue;
                }

                Evenement::create([
                    'ec_id'       => $creneau->ec_id,
                    'filiere_id'  => $creneau->filiere_id,
                    'annee_id'    => $creneau->annee_id,
                    'date'        => $date->format('Y-m-d'),
                    'heure_debut' => $creneau->heure_debut,
                    'heure_fin'   => $creneau->heure_fin,
                    'salle'       => $creneau->salle_libelle,
                    'salle_id'    => $creneau->salle_id,
                    'statut'      => 'planifie',
                ]);

                $total++;
            }
        }

        $this->info("Événements générés : {$total}");
        $this->line("  Période : {$startDate->format('d/m/Y')} → {$endDate->format('d/m/Y')}");
        if ($ignored > 0) {
            $this->line("  Ignorés (déjà existants) : {$ignored}");
        }

        return Command::SUCCESS;
    }
}
