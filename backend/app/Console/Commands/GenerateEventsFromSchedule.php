<?php

namespace App\Console\Commands;

use App\Models\EmploiDuTemps;
use App\Models\Evenement;
use App\Services\ScheduleSlotResolver;
use App\Services\RegleSeanceService;
use App\Services\Planning\Calendrier;
use App\Models\PeriodeSemestre;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateEventsFromSchedule extends Command
{
    protected $signature = 'events:generate-from-schedule
                            {--date= : Date cible (par défaut : aujourd\'hui)}
                            {--days=7 : Nombre de jours à générer}
                            {--force : Générer même pour les dates passées}';

    protected $description = 'Génère les événements (cours) depuis l\'emploi du temps pour les dates à venir';

    public function handle(ScheduleSlotResolver $resolver, RegleSeanceService $volumes, Calendrier $calendrier, \App\Services\Planning\Conflits $conflits): int
    {
        $dateStr = $this->option('date') ?? now()->format('Y-m-d');
        $days = (int) $this->option('days');
        $force = $this->option('force');

        $startDate = Carbon::parse($dateStr);
        $endDate = $startDate->copy()->addDays($days - 1);

        // Refuser seulement une date ANTÉRIEURE à aujourd'hui. isPast() comparait
        // à l'instant présent : aujourd'hui, lu comme minuit, était « passé »
        // dès 00:00:01, et la génération planifiée de 00:05 échouait chaque nuit.
        if (!$force && $startDate->copy()->startOfDay()->lt(today())) {
            $this->warn("La date {$dateStr} est dans le passé. Utilise --force pour générer quand même.");
            return Command::FAILURE;
        }

        // Seuls les créneaux de l'année active de chaque établissement. On
        // prenait tous les créneaux, toutes années confondues, en ne regardant
        // que le jour de la semaine : après la fin d'une année, ses cours
        // continuaient d'être générés, et ceux de l'année suivante l'étaient en
        // même temps. Suivre l'année ACTIVE plutôt que ses dates laisse une
        // faculté en retard finir son année.
        $anneeActive = [];
        $creneaux = EmploiDuTemps::with(['ec.ue.filieres:id,code', 'filiere', 'salle:id,nom', 'groupe.filiere:id,code'])->get()
            ->filter(function (EmploiDuTemps $creneau) use (&$anneeActive) {
                $etablissementId = $creneau->filiere?->etablissement_id;
                $anneeActive[$etablissementId ?? 0] ??= \App\Models\AnneeAcademique::activePour($etablissementId)?->id ?? 0;

                return (int) $creneau->annee_id === (int) $anneeActive[$etablissementId ?? 0];
            })
            ->values();

        if ($creneaux->isEmpty()) {
            $this->warn("Aucun créneau dans l'emploi du temps de l'année active des établissements.");
            return Command::FAILURE;
        }

        $total = 0;
        $ignored = 0;
        $skippedVolume = 0;
        $horsPeriode = 0;
        $jourFerme = 0;
        // Établissements et parités sans période de semestre : rien n'y est généré.
        $sansPeriode = [];
        $enConflit = 0;
        $detailsConflits = [];
        // Heures créées pendant CETTE exécution, par EC : la base ne les
        // reflète pas encore quand on contrôle le créneau suivant, et la
        // boucle génère plusieurs jours d'affilée.
        $consommeesParEc = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $creneauxDuJour = $resolver->filtrerPourDate($creneaux, $date);
            // Séances déjà posées ce jour-là, chargées au premier créneau qui en a besoin.
            $occupationsDuJour = null;

            foreach ($creneauxDuJour as $creneau) {
                // Calendrier : la période du semestre de l'UE, hors fermetures.
                // Le jour de la semaine seul générait des séances pendant les
                // vacances, les examens et les semestres pas encore commencés.
                $etablissementId = $creneau->filiere?->etablissement_id;
                $semestre = $creneau->ec?->ue?->semestre;
                $motif = $calendrier->motifDeRelache($etablissementId, (int) $creneau->annee_id, $semestre, $date);

                if ($motif === Calendrier::SANS_PERIODE) {
                    $sansPeriode[($etablissementId ?? 0) . '|' . PeriodeSemestre::pariteDe($semestre ?: 1)] = true;
                    continue;
                }

                if ($motif === Calendrier::HORS_PERIODE) {
                    $horsPeriode++;
                    continue;
                }

                if ($motif === Calendrier::FERMETURE) {
                    $jourFerme++;
                    continue;
                }

                // Par groupe : deux groupes de TD du même cours à la même heure
                // sont deux séances.
                $existe = Evenement::where('ec_id', $creneau->ec_id)
                    ->where('date', $date->format('Y-m-d'))
                    ->where('heure_debut', $creneau->heure_debut)
                    ->when($creneau->groupe_id, fn ($q) => $q->where('groupe_id', $creneau->groupe_id), fn ($q) => $q->whereNull('groupe_id'))
                    ->exists();

                if ($existe) {
                    $ignored++;
                    continue;
                }

                // Un créneau qui gênerait une séance déjà posée (salle, promotion,
                // groupe) n'est pas généré : il est signalé.
                $occupationsDuJour ??= Evenement::with([...\App\Services\Planning\Conflits::CHARGEMENTS, 'salleRef:id,nom'])
                    ->whereDate('date', $date->toDateString())
                    ->where('statut', '!=', 'annule')
                    ->get()
                    ->map(fn (Evenement $e) => $conflits->occupation($e))
                    ->all();
                $occupation = ['id' => null] + $conflits->occupation($creneau);
                $motifsConflit = $conflits->entre($occupation, $occupationsDuJour, 'le ' . $date->format('d/m/Y'));

                if ($motifsConflit !== []) {
                    $enConflit++;
                    $detailsConflits[] = ($creneau->ec?->code ?? '?') . ' : ' . $motifsConflit[0];
                    continue;
                }

                $attributs = $resolver->versAttributsEvenement($creneau, $date);

                // Refus si le créneau dépasse le volume restant de l'EC : la
                // règle vaut ici comme dans le formulaire, sinon la génération
                // planifiée la contournerait chaque nuit.
                // Par type de séance : un TD consomme le volume de TD, pas celui du CM.
                $type = $attributs['type_cours'];

                if ($creneau->ec) {
                    $deja = $consommeesParEc[$creneau->ec_id] ?? [];
                    if ($volumes->refus(
                        $creneau->ec, $attributs['date'], $attributs['heure_debut'], $attributs['heure_fin'], null, $deja, $type, $attributs['groupe_id']
                    )) {
                        $skippedVolume++;
                        continue;
                    }
                }

                $cree = Evenement::create($attributs);
                $occupationsDuJour[] = ['id' => $cree->id] + $occupation;
                $consommeesParEc[$creneau->ec_id][] = [
                    'type'      => $type,
                    'groupe_id' => $attributs['groupe_id'],
                    'heures'    => RegleSeanceService::duree($attributs['heure_debut'], $attributs['heure_fin']),
                ];

                $total++;
            }
        }

        $this->info("Événements générés : {$total}");
        $this->line("  Période : {$startDate->format('d/m/Y')} → {$endDate->format('d/m/Y')}");
        if ($ignored > 0) {
            $this->line("  Ignorés (déjà existants) : {$ignored}");
        }
        if ($skippedVolume > 0) {
            $this->line("  Ignorés (volume horaire dépassé) : {$skippedVolume}");
        }
        if ($enConflit > 0) {
            $this->warn("  Ignorés (conflit avec une séance déjà posée) : {$enConflit}");
            foreach (array_slice($detailsConflits, 0, 5) as $detail) {
                $this->line("    - {$detail}");
            }
        }
        if ($jourFerme > 0) {
            $this->line("  Ignorés (jour de fermeture) : {$jourFerme}");
        }
        if ($horsPeriode > 0) {
            $this->line("  Ignorés (hors de la période du semestre) : {$horsPeriode}");
        }
        if ($sansPeriode !== []) {
            $codes = \App\Models\Etablissement::pluck('code', 'id');
            $manques = array_map(function (string $cle) use ($codes) {
                [$etablissement, $parite] = explode('|', $cle);

                return ($codes[(int) $etablissement] ?? 'filières sans établissement') . " (semestres {$parite}s)";
            }, array_keys($sansPeriode));

            $this->warn('  Ignorés : aucune période de semestre déclarée pour ' . implode(', ', $manques) . '. Déclarez-la dans Paramètres › Calendrier.');
        }

        return Command::SUCCESS;
    }
}
