<?php

namespace App\Services;

use App\Models\Ec;
use App\Models\Evenement;
use App\Models\Groupe;
use App\Models\Ue;
use App\Support\TypeCours;
use Illuminate\Support\Collection;

/**
 * Avancement d'un cours — non démarré, en cours, terminé — calculé à la lecture
 * depuis ses séances terminées.
 *
 * Le statut était une colonne que recalculait chaque nuit ecs:sync-statut :
 * planificateur arrêté, il dérivait sans que rien ne le dise. Il ne comptait
 * que le total des heures, sans les types ni les groupes. Il se calcule
 * désormais à chaque lecture, en une requête pour toute une liste.
 *
 * Un TD ou un TP est fait quand chaque groupe l'a suivi : c'est le groupe le
 * moins avancé qui compte. Une évaluation ne consomme pas de volume.
 */
class AvancementCours
{
    public const NON_DEMARRE = 'non_demarre';
    public const EN_COURS = 'en_cours';
    public const TERMINE = 'termine';

    private const EPSILON = 0.001;

    /**
     * Pose sur chaque EC son statut et ses heures faites par type.
     *
     * @param  iterable<Ec>  $ecs
     */
    public function appliquer(iterable $ecs): void
    {
        $ecs = collect($ecs)->filter()->values();

        if ($ecs->isEmpty()) {
            return;
        }

        $seances = Evenement::query()
            ->whereIn('ec_id', $ecs->pluck('id'))
            ->where('statut', 'termine')
            ->get(['ec_id', 'type_cours', 'groupe_id', 'heure_debut', 'heure_fin'])
            ->groupBy('ec_id');

        // L'UE de chaque EC est lue à part : poser une relation sur les modèles
        // reçus changerait ce que les ressources sérialisent. Une UE chargée par
        // ce calcul, sans statut, le recalculait en chargeant ses EC, qui
        // portaient une UE… jusqu'à épuiser la profondeur du JSON.
        $ues = Ue::with('filieres:id')
            ->whereIn('id', $ecs->pluck('ue_id')->unique()->values())
            ->get(['id', 'annee_id'])
            ->keyBy('id');

        // Groupes de TD et de TP des filières qui suivent chaque cours, pour son année.
        $groupes = Groupe::query()
            ->whereIn('annee_id', $ues->pluck('annee_id')->unique()->values())
            ->whereIn('filiere_id', $ues->flatMap(fn (Ue $ue) => $ue->filieres->pluck('id'))->unique()->values())
            ->get(['id', 'filiere_id', 'annee_id', 'type'])
            ->groupBy(fn (Groupe $g) => "{$g->filiere_id}|{$g->annee_id}|{$g->type}");

        foreach ($ecs as $ec) {
            $faites = $this->faites($ues->get($ec->ue_id), $seances->get($ec->id, collect()), $groupes);
            $ec->setAttribute('heures_faites', array_map(fn (float $h) => round($h, 2), $faites));
            $ec->setAttribute('statut', $this->statut($ec, $faites));
        }
    }

    /**
     * Pose le statut de chaque UE, et celui de ses EC.
     *
     * @param  iterable<Ue>  $ues
     */
    public function appliquerAuxUes(iterable $ues): void
    {
        $ues = collect($ues)->filter()->values();

        // Les EC déjà chargés servent ; sinon ils sont lus à part, sans être
        // posés sur l'UE (voir appliquer()).
        $ecsParUe = $ues->mapWithKeys(fn (Ue $ue) => [
            $ue->id => $ue->relationLoaded('ecs') ? $ue->ecs : Ec::where('ue_id', $ue->id)->get(),
        ]);
        $this->appliquer($ecsParUe->flatMap(fn ($ecs) => $ecs));

        foreach ($ues as $ue) {
            $ue->setAttribute('statut', self::statutUe($ecsParUe[$ue->id]->map(fn (Ec $ec) => $ec->getAttributes()['statut'])));
        }
    }

    public function statutDe(Ec $ec): string
    {
        $this->appliquer([$ec]);

        return $ec->getAttributes()['statut'];
    }

    public function statutDeUe(Ue $ue): string
    {
        $this->appliquerAuxUes([$ue]);

        return $ue->getAttributes()['statut'];
    }

    /** Terminée quand tous ses EC le sont ; en cours dès que l'un a commencé. */
    public static function statutUe(Collection $statuts): string
    {
        return match (true) {
            $statuts->isEmpty()                                                    => self::NON_DEMARRE,
            $statuts->every(fn ($s) => $s === self::TERMINE)                       => self::TERMINE,
            $statuts->contains(self::EN_COURS) || $statuts->contains(self::TERMINE) => self::EN_COURS,
            default                                                                => self::NON_DEMARRE,
        };
    }

    /**
     * Heures faites par type. Pour les TD et les TP : celles de toute la
     * promotion, plus celles du groupe le moins avancé.
     *
     * @return array{cm: float, td: float, tp: float}
     */
    private function faites(?Ue $ue, Collection $seances, Collection $groupes): array
    {
        $faites = [TypeCours::CM => 0.0, TypeCours::TD => 0.0, TypeCours::TP => 0.0];
        $parGroupe = [TypeCours::TD => [], TypeCours::TP => []];

        foreach ($seances as $seance) {
            $type = TypeCours::normaliser($seance->type_cours);

            // Une évaluation ne consomme pas de volume.
            if (!array_key_exists($type, $faites)) {
                continue;
            }

            $heures = RegleSeanceService::duree($seance->heure_debut, $seance->heure_fin);

            if ($seance->groupe_id === null || $type === TypeCours::CM) {
                $faites[$type] += $heures;
            } else {
                $parGroupe[$type][$seance->groupe_id] = ($parGroupe[$type][$seance->groupe_id] ?? 0.0) + $heures;
            }
        }

        foreach ([TypeCours::TD, TypeCours::TP] as $type) {
            $ids = $ue
                ? $ue->filieres->flatMap(fn ($f) => $groupes->get("{$f->id}|{$ue->annee_id}|{$type}", collect())->pluck('id'))->all()
                : [];

            if ($ids !== []) {
                $faites[$type] += min(array_map(fn ($id) => $parGroupe[$type][$id] ?? 0.0, $ids));
            } elseif ($parGroupe[$type] !== []) {
                // Séances d'un groupe supprimé depuis : le plus avancé fait foi.
                $faites[$type] += max($parGroupe[$type]);
            }
        }

        return $faites;
    }

    private function statut(Ec $ec, array $faites): string
    {
        $total = array_sum($faites);

        if ($total <= self::EPSILON) {
            return self::NON_DEMARRE;
        }

        // Volume pas encore ventilé : seul le total fait foi.
        if ($ec->volume_a_ventiler) {
            return $total + self::EPSILON >= (float) $ec->volume_horaire ? self::TERMINE : self::EN_COURS;
        }

        return max(RegleSeanceService::restantesDepuis($ec, $faites)) <= self::EPSILON ? self::TERMINE : self::EN_COURS;
    }
}
