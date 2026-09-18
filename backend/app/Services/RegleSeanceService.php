<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ec;
use App\Models\Evenement;
use App\Support\TypeCours;

/**
 * Les règles qu'une séance doit respecter, réunies en un seul endroit.
 *
 *  1. Durée : une séance ne dépasse pas le plafond de config/presence.php.
 *  2. Volume : la somme des séances d'un EC ne dépasse pas son volume horaire.
 *  3. Chevauchement : deux séances d'un même cours ne se superposent pas.
 *
 * Une seule définition, partagée par les quatre chemins qui créent ou modifient
 * un événement — formulaire, édition, génération planifiée, validation d'un
 * import IA. Écrire la règle dans l'écran seul l'aurait laissée contournable
 * par tous les autres.
 *
 * Sont décomptées toutes les séances NON ANNULÉES, planifiées comprises. Ne
 * compter que les séances déjà tenues laisserait réserver un volume illimité
 * tant qu'aucun cours n'a eu lieu.
 *
 * Rien n'interdit plusieurs séances d'un même cours dans une journée : le
 * plafond porte sur la durée d'un créneau, jamais sur leur nombre.
 *
 * Le volume se compte par type de séance (CM, TD, TP) dès que l'EC est
 * ventilé. Les TD et les TP puisent dans la réserve TP/TD — les maquettes qui
 * ne séparent pas les deux — une fois leur propre volume épuisé. Une évaluation
 * ne consomme rien. Un EC « à ventiler » garde l'ancienne règle : son total.
 */
class RegleSeanceService
{
    /** Une séance annulée libère son créneau. */
    private const STATUTS_NON_DECOMPTES = ['annule'];

    /** Tolérance d'arrondi : les heures sont saisies à la minute. */
    private const EPSILON = 0.001;

    /** Durée d'un créneau, en heures, à partir de deux « H:i » ou « H:i:s ». */
    public static function duree(string $debut, string $fin): float
    {
        return (self::enMinutes($fin) - self::enMinutes($debut)) / 60;
    }

    private static function enMinutes(string $heure): int
    {
        $parties = array_pad(explode(':', $heure), 2, '0');

        return ((int) $parties[0]) * 60 + (int) $parties[1];
    }

    /**
     * Heures déjà réservées pour cet EC.
     *
     * $evenementExclu sert à l'édition : l'événement que l'on modifie ne doit
     * pas se compter lui-même, sinon rallonger un créneau serait toujours refusé.
     */
    public function heuresReservees(int $ecId, ?int $evenementExclu = null): float
    {
        return array_sum($this->reserveesParType($ecId, $evenementExclu));
    }

    /** @return array<string, float> heures réservées par type de séance */
    public function reserveesParType(int $ecId, ?int $evenementExclu = null): array
    {
        return Evenement::query()
            ->where('ec_id', $ecId)
            ->whereNotIn('statut', self::STATUTS_NON_DECOMPTES)
            ->when($evenementExclu !== null, fn ($q) => $q->where('id', '!=', $evenementExclu))
            ->get(['heure_debut', 'heure_fin', 'type_cours'])
            ->groupBy('type_cours')
            ->map(fn ($g) => (float) $g->sum(fn ($e) => self::duree($e->heure_debut, $e->heure_fin)))
            ->all();
    }

    /**
     * Heures restantes par type à partir des heures déjà prises, réserve TP/TD
     * comprise. Fonction pure : la liste des EC s'en sert sans une requête par EC.
     *
     * @param  array<string, float>  $prisParType
     * @return array{cm: float, td: float, tp: float}
     */
    public static function restantesDepuis(Ec $ec, array $prisParType): array
    {
        $pris = fn (string $type) => (float) ($prisParType[$type] ?? 0);

        $debordTd = max(0.0, $pris(TypeCours::TD) - $ec->volume_td);
        $debordTp = max(0.0, $pris(TypeCours::TP) - $ec->volume_tp);
        $reserve  = max(0.0, $ec->volume_td_tp - $debordTd - $debordTp);

        return [
            TypeCours::CM => max(0.0, $ec->volume_cm - $pris(TypeCours::CM)),
            TypeCours::TD => max(0.0, $ec->volume_td - $pris(TypeCours::TD)) + $reserve,
            TypeCours::TP => max(0.0, $ec->volume_tp - $pris(TypeCours::TP)) + $reserve,
        ];
    }

    /**
     * Heures prises par type, à partir de séances [type, groupe_id, heures].
     *
     * Chaque groupe reçoit tout le volume de TD ou de TP de l'EC. Pour un
     * groupe, on compte les séances de toute la promotion et les siennes ; pour
     * une séance de toute la promotion, le groupe le plus avancé — elle compte
     * pour chacun. Le CM réunit toujours la promotion ; une évaluation ne
     * consomme rien.
     *
     * @param  iterable<array{type: string, groupe_id: ?int, heures: float}>  $seances
     * @return array{cm: float, td: float, tp: float}
     */
    public static function prisDepuis(iterable $seances, ?int $groupeId = null, ?string $pourType = null): array
    {
        $promotion = [TypeCours::CM => 0.0, TypeCours::TD => 0.0, TypeCours::TP => 0.0];
        $parGroupe = [TypeCours::TD => [], TypeCours::TP => []];

        foreach ($seances as $seance) {
            $type = $seance['type'] ?? TypeCours::CM;
            $heures = (float) ($seance['heures'] ?? 0);
            $groupe = $seance['groupe_id'] ?? null;

            if (!isset($promotion[$type])) {
                continue;
            }

            if ($groupe === null || $type === TypeCours::CM) {
                $promotion[$type] += $heures;
            } else {
                $parGroupe[$type][(int) $groupe] = ($parGroupe[$type][(int) $groupe] ?? 0.0) + $heures;
            }
        }

        foreach ([TypeCours::TD, TypeCours::TP] as $type) {
            $promotion[$type] += ($groupeId !== null && $pourType === $type)
                ? ($parGroupe[$type][$groupeId] ?? 0.0)
                : max([0.0, ...array_values($parGroupe[$type])]);
        }

        return $promotion;
    }

    /** Séances de l'EC qui consomment son volume, au format de prisDepuis(). */
    private function seancesDe(int $ecId, ?int $evenementExclu = null): array
    {
        return Evenement::query()
            ->where('ec_id', $ecId)
            ->whereNotIn('statut', self::STATUTS_NON_DECOMPTES)
            ->when($evenementExclu !== null, fn ($q) => $q->where('id', '!=', $evenementExclu))
            ->get(['heure_debut', 'heure_fin', 'type_cours', 'groupe_id'])
            ->map(fn ($e) => ['type' => $e->type_cours, 'groupe_id' => $e->groupe_id, 'heures' => self::duree($e->heure_debut, $e->heure_fin)])
            ->all();
    }

    /**
     * Séances créées plus tôt dans un même traitement : une liste au format de
     * prisDepuis(), ou des heures par type (séances de toute la promotion).
     */
    private static function seancesDuLot(array $lot): array
    {
        if (array_is_list($lot)) {
            return $lot;
        }

        return array_map(fn ($type, $heures) => ['type' => $type, 'groupe_id' => null, 'heures' => (float) $heures], array_keys($lot), $lot);
    }

    /**
     * Heures restantes par type, pour la promotion ou pour un groupe ; null pour
     * un EC « à ventiler », dont seul le total fait foi.
     *
     * @param  array  $dejaDansLeLot  séances créées plus tôt dans le même traitement (voir seancesDuLot)
     * @return array{cm: float, td: float, tp: float}|null
     */
    public function restantesParType(Ec $ec, ?int $evenementExclu = null, array $dejaDansLeLot = [], ?int $groupeId = null, ?string $pourType = null): ?array
    {
        if ($ec->volume_a_ventiler) {
            return null;
        }

        $seances = [...$this->seancesDe($ec->id, $evenementExclu), ...self::seancesDuLot($dejaDansLeLot)];

        return self::restantesDepuis($ec, self::prisDepuis($seances, $groupeId, $pourType));
    }

    public function heuresRestantes(Ec $ec, ?int $evenementExclu = null): float
    {
        return (float) $ec->volume_horaire - $this->heuresReservees($ec->id, $evenementExclu);
    }

    /**
     * Message de refus si le créneau enfreint une règle, null s'il passe.
     *
     * Deux règles, dans cet ordre : la durée maximale d'une séance, puis le
     * volume horaire restant de l'EC. La première d'abord, parce que son message
     * est plus précis — dire « il ne reste que 2h » à qui demande une séance de
     * douze heures désigne la mauvaise cause.
     *
     * $consommeesDansCeLot permet aux traitements par lot de tenir compte des
     * créneaux créés plus tôt dans la même boucle : la base ne les reflète pas
     * encore au moment où l'on contrôle le suivant. Un nombre vaut pour le type
     * de la séance ; un tableau donne les heures par type.
     *
     * @param  float|array<string, float>  $consommeesDansCeLot
     */
    public function refus(
        Ec $ec,
        string $date,
        string $debut,
        string $fin,
        ?int $evenementExclu = null,
        float|array $consommeesDansCeLot = 0.0,
        string $type = TypeCours::CM,
        ?int $groupeId = null
    ): ?string {
        $demandees = self::duree($debut, $fin);

        // 1. Durée de la séance. Contrainte distincte du volume : celui-ci borne
        //    le TOTAL des séances, celle-là borne CHACUNE. Un cours à qui il
        //    reste dix-huit heures acceptait sinon un créneau de 8h à 23h.
        $max = (float) config('presence.seance.duree_max_heures');
        if ($max > 0 && $demandees > $max + self::EPSILON) {
            return sprintf(
                'Une séance ne peut pas durer plus de %s. Le créneau demandé en dure %s.',
                self::format($max),
                self::format($demandees)
            );
        }

        // 2. Chevauchement avec une autre séance du même cours ce jour-là.
        if ($autre = $this->seanceQuiChevauche($ec, $date, $debut, $fin, $evenementExclu, $groupeId)) {
            return sprintf(
                'Une séance de %s est déjà programmée ce jour-là de %s à %s. Deux séances du même '
                . 'cours ne peuvent pas se chevaucher — décalez le créneau.',
                $ec->code,
                substr((string) $autre->heure_debut, 0, 5),
                substr((string) $autre->heure_fin, 0, 5)
            );
        }

        // 3. Volume horaire restant de l'EC. Une évaluation n'en consomme pas.
        if ($type === TypeCours::EVALUATION) {
            return null;
        }

        $lot = is_array($consommeesDansCeLot) ? $consommeesDansCeLot : [$type => $consommeesDansCeLot];
        $parType = $this->restantesParType($ec, $evenementExclu, $lot, $groupeId, $type);

        if ($parType !== null) {
            $groupe = $groupeId ? \App\Models\Groupe::whereKey($groupeId)->value('libelle') : null;

            return $this->refusParType($ec, $type, $demandees, $parType[$type] ?? 0.0, $groupe);
        }

        $restantes = $this->heuresRestantes($ec, $evenementExclu) - array_sum(array_column(self::seancesDuLot($lot), 'heures'));

        if ($demandees <= $restantes + self::EPSILON) {
            return null;
        }

        if ($restantes <= self::EPSILON) {
            return sprintf(
                "L'EC %s (%s) a épuisé son volume horaire de %s : aucune séance supplémentaire ne peut être programmée.",
                $ec->code,
                $ec->intitule,
                self::format((float) $ec->volume_horaire)
            );
        }

        return sprintf(
            'Le créneau demandé dure %s, mais il ne reste que %s sur les %s prévues pour l\'EC %s (%s).',
            self::format($demandees),
            self::format($restantes),
            self::format((float) $ec->volume_horaire),
            $ec->code,
            $ec->intitule
        );
    }

    /** Refus sur le volume d'un type (EC ventilé) ; null si la séance tient. */
    private function refusParType(Ec $ec, string $type, float $demandees, float $restantes, ?string $groupe = null): ?string
    {
        if ($demandees <= $restantes + self::EPSILON) {
            return null;
        }

        // Chaque groupe a son propre volume de TD ou de TP : le message le nomme.
        $libelle = (TypeCours::LIBELLES[$type] ?? $type) . ($groupe ? " du groupe {$groupe}" : '');
        $prevues = match ($type) {
            TypeCours::TD => $ec->volume_td + $ec->volume_td_tp,
            TypeCours::TP => $ec->volume_tp + $ec->volume_td_tp,
            default       => $ec->volume_cm,
        };

        if ($restantes <= self::EPSILON) {
            return sprintf(
                "L'EC %s (%s) a épuisé son volume de %s (%s) : aucune séance de %s supplémentaire ne peut être programmée.",
                $ec->code,
                $ec->intitule,
                $libelle,
                self::format((float) $prevues),
                TypeCours::LIBELLES[$type] ?? $type
            );
        }

        return sprintf(
            'Le créneau demandé dure %s, mais il ne reste que %s de %s sur les %s prévues pour l\'EC %s (%s).',
            self::format($demandees),
            self::format($restantes),
            $libelle,
            self::format((float) $prevues),
            $ec->code,
            $ec->intitule
        );
    }

    /**
     * Séance du même cours qui recouvre ce créneau ce jour-là, s'il en existe une.
     *
     * Plusieurs séances par jour sont permises, mais pas simultanées : les mêmes
     * étudiants ne peuvent pas être à deux endroits. Deux créneaux qui se touchent
     * (10h-12h après 08h-10h) ne se chevauchent PAS — la condition est stricte des
     * deux côtés. Une séance annulée ne bloque rien.
     *
     * Deux groupes différents, eux, suivent le même cours au même moment : une
     * séance de groupe ne bute que sur celles de toute la promotion et sur
     * celles de son groupe.
     */
    public function seanceQuiChevauche(
        Ec $ec,
        string $date,
        string $debut,
        string $fin,
        ?int $evenementExclu = null,
        ?int $groupeId = null
    ): ?Evenement {
        return Evenement::query()
            ->where('ec_id', $ec->id)
            ->where('date', $date)
            ->when($groupeId !== null, fn ($q) => $q->where(fn ($g) => $g->whereNull('groupe_id')->orWhere('groupe_id', $groupeId)))
            ->whereNotIn('statut', self::STATUTS_NON_DECOMPTES)
            ->when($evenementExclu !== null, fn ($q) => $q->where('id', '!=', $evenementExclu))
            ->where('heure_debut', '<', self::normaliser($fin))
            ->where('heure_fin', '>', self::normaliser($debut))
            ->first();
    }

    /**
     * Applique la condition de chevauchement STRICT à une requête sur des créneaux.
     *
     * Partagée entre les événements datés et les créneaux hebdomadaires : la
     * même règle écrite deux fois a divergé une fois déjà — l'import CSV
     * utilisait un « whereBetween », inclusif, et refusait deux cours qui se
     * suivent (10h-12h après 8h-10h) comme s'ils se chevauchaient.
     */
    public static function filtreChevauchement($requete, string $debut, string $fin)
    {
        return $requete->where('heure_debut', '<', self::normaliser($fin))
            ->where('heure_fin', '>', self::normaliser($debut));
    }

    /** « 8:00 » -> « 08:00:00 », format des colonnes TIME. */
    private static function normaliser(string $heure): string
    {
        $parties = array_pad(explode(':', $heure), 3, '00');

        return sprintf('%02d:%02d:%02d', (int) $parties[0], (int) $parties[1], (int) $parties[2]);
    }

    /** « 2h », « 1h30 » : plus lisible qu'un décimal dans un message destiné à un humain. */
    public static function format(float $heures): string
    {
        $minutes = (int) round($heures * 60);
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m === 0 ? "{$h}h" : sprintf('%dh%02d', $h, $m);
    }
}
