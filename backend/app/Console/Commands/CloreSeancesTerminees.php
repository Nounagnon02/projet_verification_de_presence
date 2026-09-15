<?php

namespace App\Console\Commands;

use App\Models\Evenement;
use App\Models\QrCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Passe à « terminé » les séances dont la fenêtre de présence est fermée.
 *
 * Rien ne le faisait : un événement restait « planifié » ou « en cours » pour
 * toujours, sauf saisie manuelle. Or le tableau de bord, le taux de présence et
 * l'avancement des EC (calculé à la lecture) ne comptent que les séances terminées.
 *
 * La borne est la fermeture du scan (Evenement::fermetureScan()), pas l'heure
 * de fin : qrcode:auto-generate ne fait tourner le QR que des séances planifiées
 * ou en cours, et clore à l'heure de fin couperait la rotation pendant les
 * dernières minutes où le scan est encore accepté.
 *
 * Une séance jamais passée « en cours » (planificateur arrêté, aucun QR) est
 * close elle aussi : son horaire est passé. Une séance qui n'a pas eu lieu se
 * déclare « annulée » ; les séances annulées ne sont jamais touchées.
 *
 * Les QR encore actifs de ces séances sont désactivés : leur token a expiré à la
 * fermeture, et qr:clean-expired ne supprime jamais un QR actif.
 *
 * Idempotente : une séance close n'est plus candidate.
 */
class CloreSeancesTerminees extends Command
{
    protected $signature = 'events:close-finished {--dry-run : Affiche les séances concernées sans rien modifier}';

    protected $description = 'Passe à « terminé » les séances dont la fenêtre de présence est fermée';

    private const STATUTS_OUVERTS = ['planifie', 'en_cours'];

    public function handle(): int
    {
        $maintenant = now();
        $simulation = (bool) $this->option('dry-run');

        // Toute séance datée d'aujourd'hui ou avant est candidate ; c'est
        // fermetureScan() qui tranche, y compris pour une séance à cheval sur
        // minuit dont la fenêtre ferme le lendemain. where() et non whereDate() :
        // `date` est une colonne DATE.
        $aClore = Evenement::whereIn('statut', self::STATUTS_OUVERTS)
            ->where('date', '<=', $maintenant->toDateString())
            ->get(['id', 'date', 'heure_debut', 'heure_fin', 'statut'])
            ->filter(fn (Evenement $e) => $maintenant->greaterThan($e->fermetureScan()));

        if ($aClore->isEmpty()) {
            $this->info('Aucune séance à clore.');

            return self::SUCCESS;
        }

        if ($simulation) {
            $this->table(
                ['Id', 'Date', 'Horaire', 'Statut actuel'],
                $aClore->map(fn (Evenement $e) => [
                    $e->id,
                    $e->date->format('Y-m-d'),
                    substr($e->heure_debut, 0, 5) . '–' . substr($e->heure_fin, 0, 5),
                    $e->statut,
                ])->values()
            );
            $this->warn(sprintf('Simulation : %d séance(s) à clore, rien n\'est modifié.', $aClore->count()));

            return self::SUCCESS;
        }

        $closes = $qrDesactives = 0;

        DB::transaction(function () use ($aClore, &$closes, &$qrDesactives) {
            foreach ($aClore->pluck('id')->chunk(500) as $ids) {
                // Statut revérifié à l'écriture : une annulation enregistrée entre
                // la lecture et ici ne doit pas être écrasée.
                $closes += Evenement::whereIn('id', $ids)
                    ->whereIn('statut', self::STATUTS_OUVERTS)
                    ->update(['statut' => 'termine']);

                $qrDesactives += QrCode::whereIn('evenement_id', $ids)
                    ->where('actif', true)
                    ->update(['actif' => false]);
            }
        });

        $this->info("Séances closes : {$closes}, QR codes désactivés : {$qrDesactives}");

        return self::SUCCESS;
    }
}
