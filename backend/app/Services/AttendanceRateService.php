<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Calcul du taux de présence.
 *
 * Le dénominateur correct n'est pas « tous les étudiants × tous les
 * événements » : un étudiant n'est attendu qu'aux événements des ECs
 * auxquels il est inscrit. On somme donc, pour chaque événement, le nombre
 * d'étudiants inscrits à son EC (pour l'année de l'événement) via la table
 * pivot etudiant_ec.
 *
 * Les deux méthodes prennent le MÊME filtre sur les événements (alias « e »)
 * pour que numérateur et dénominateur portent sur le même périmètre.
 */
class AttendanceRateService
{
    /**
     * Nombre de présences attendues : somme, sur les événements filtrés, des
     * étudiants inscrits à l'EC de l'événement pour son année.
     *
     * @param  Closure(\Illuminate\Database\Query\Builder):void  $eventFilter
     */
    public function expected(Closure $eventFilter): int
    {
        $query = DB::table('evenements as e')
            ->join('etudiant_ec as ee', function ($join) {
                $join->on('ee.ec_id', '=', 'e.ec_id')
                     ->on('ee.annee_id', '=', 'e.annee_id');
            })
            ->join('etudiants as s', 's.id', '=', 'ee.etudiant_id')
            ->whereNull('e.deleted_at')
            ->whereNull('s.deleted_at');

        $eventFilter($query);

        return $query->count();
    }

    /**
     * Nombre de présences validées sur le même périmètre d'événements.
     *
     * @param  Closure(\Illuminate\Database\Query\Builder):void  $eventFilter
     */
    public function recorded(Closure $eventFilter): int
    {
        $query = DB::table('presences as p')
            ->join('evenements as e', 'e.id', '=', 'p.evenement_id')
            ->whereNull('p.deleted_at')
            ->whereNull('e.deleted_at')
            ->where('p.statut', 'valide');

        $eventFilter($query);

        return $query->count();
    }

    /**
     * Taux de présence (%) arrondi à une décimale, pour le filtre donné.
     */
    public function rate(Closure $eventFilter): float
    {
        $attendus = $this->expected($eventFilter);

        if ($attendus === 0) {
            return 0.0;
        }

        return round(($this->recorded($eventFilter) / $attendus) * 100, 1);
    }
}
