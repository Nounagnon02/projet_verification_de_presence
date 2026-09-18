<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Evenement;
use App\Services\CorrespondanceSalles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rattache aux salles configurées les séances qui n'ont qu'un nom de salle saisi.
 *
 * Tant qu'une séance ne désigne sa salle que par un texte libre, ni le contrôle
 * GPS/Wi-Fi ni la détection de double réservation ne peuvent s'appliquer : les
 * deux reposent sur salle_id. Pour chaque nom distinct, la commande réutilise la
 * salle de l'établissement qui porte ce nom (ou ce code), et la crée sinon —
 * avec la même règle que les imports, portée par CorrespondanceSalles.
 *
 * Une salle créée ici n'a ni coordonnées GPS ni réseau Wi-Fi : ces données ne se
 * devinent pas, elles se relèvent sur place. Elle vérifie donc par QR code seul
 * jusqu'à ce que l'administration la complète.
 *
 * Idempotente : une seconde exécution ne trouve plus rien à rattacher.
 */
class RattacherSallesSaisies extends Command
{
    protected $signature = 'salles:rattacher {--dry-run : Affiche le plan sans rien modifier}';

    protected $description = 'Crée les salles manquantes à partir des noms saisis et y rattache les séances';

    public function handle(): int
    {
        $simulation = (bool) $this->option('dry-run');

        $orphelines = Evenement::with('filiere:id,etablissement_id')
            ->whereNull('salle_id')
            ->whereNotNull('salle')
            ->where('salle', '!=', '')
            ->get(['id', 'filiere_id', 'salle']);

        // Une salle appartient à un établissement, déduit de la filière. Une
        // séance dont la filière n'en a pas ne peut être rattachée à rien : elle
        // est écartée et signalée plutôt que de faire échouer toute la reprise.
        $sansEtablissement = $orphelines->filter(fn (Evenement $e) => !$e->filiere?->etablissement_id);
        $orphelines = $orphelines->reject(fn (Evenement $e) => !$e->filiere?->etablissement_id);

        if ($orphelines->isEmpty()) {
            $this->info('Aucune séance à rattacher : toutes ont une salle configurée ou aucune salle.');
        }

        // Une même salle peut avoir été saisie avec une casse ou des espaces
        // différents : le regroupement se fait sur une forme normalisée.
        $groupes = $orphelines->groupBy(
            fn (Evenement $e) => $e->filiere->etablissement_id . '|' . CorrespondanceSalles::cle($e->salle)
        );

        $creees = $reutilisees = $rattachees = 0;
        $lignes = [];
        $correspondances = [];

        DB::beginTransaction();

        try {
            foreach ($groupes as $cleGroupe => $evenements) {
                [$etablissementId] = explode('|', $cleGroupe, 2);
                $nom = trim($evenements->first()->salle);

                $correspondance = $correspondances[$etablissementId] ??= CorrespondanceSalles::pour((int) $etablissementId);
                $salle = $correspondance->trouver($nom);

                if ($salle) {
                    $reutilisees++;
                    $statut = "existante (#{$salle->id})";
                } else {
                    $creees++;
                    $statut = 'à créer';
                    if (!$simulation) {
                        [$salle] = $correspondance->trouverOuCreer($nom);
                        $statut = "créée (#{$salle->id}, code {$salle->code})";
                    }
                }

                if (!$simulation) {
                    // Le nom recopié est celui de la salle : tous les écrans qui
                    // lisent evenements.salle affichent ainsi la même chose.
                    Evenement::whereIn('id', $evenements->pluck('id'))
                        ->update(['salle_id' => $salle->id, 'salle' => $salle->nom]);
                }

                $rattachees += $evenements->count();
                $lignes[] = [$nom, $statut, $evenements->count()];
            }

            // Séances déjà rattachées dont le nom recopié diverge de la salle.
            $divergentes = Evenement::with('salleRef:id,nom')
                ->whereNotNull('salle_id')
                ->get(['id', 'salle_id', 'salle'])
                ->filter(fn (Evenement $e) => $e->salleRef && trim((string) $e->salle) !== $e->salleRef->nom);

            if (!$simulation) {
                foreach ($divergentes as $e) {
                    $e->update(['salle' => $e->salleRef->nom]);
                }
            }

            $simulation ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if ($lignes) {
            $this->table(['Nom saisi', 'Salle', 'Séances'], $lignes);
        }

        $this->line(sprintf(
            '%s : %d salle(s) %s, %d réutilisée(s), %d séance(s) rattachée(s), %d nom(s) de salle réaligné(s).',
            $simulation ? 'Simulation' : 'Terminé',
            $creees,
            $simulation ? 'à créer' : 'créée(s)',
            $reutilisees,
            $rattachees,
            $divergentes->count()
        ));

        if ($sansEtablissement->isNotEmpty()) {
            $this->warn(sprintf(
                '%d séance(s) ignorée(s) : leur filière n\'est rattachée à aucun établissement (ids %s).',
                $sansEtablissement->count(),
                $sansEtablissement->pluck('id')->implode(', ')
            ));
        }

        if ($creees && !$simulation) {
            $this->warn('Les salles créées n\'ont ni GPS ni Wi-Fi : à compléter dans Paramètres > Salles.');
        }

        return self::SUCCESS;
    }

}
