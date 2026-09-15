<?php

namespace App\Services;

use Closure;
use Illuminate\Database\Query\Builder;
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
 * Toutes les méthodes prennent le MÊME filtre sur les événements (alias « e »)
 * pour que numérateur et dénominateur portent sur le même périmètre. Les
 * variantes « By » regroupent le calcul (par UE, par semaine, par filière) :
 * le tableau de bord et les rapports partagent ainsi une seule définition.
 */
class AttendanceRateService
{
    /**
     * Nombre de présences attendues : somme, sur les événements filtrés, des
     * étudiants inscrits à l'EC de l'événement pour son année.
     *
     * @param  Closure(Builder):void  $eventFilter
     */
    public function expected(Closure $eventFilter): int
    {
        $query = $this->baseAttendus();

        $eventFilter($query);

        return $query->count();
    }

    /**
     * Présences attendues regroupées : [valeur de $cle => nombre]. $cle est une
     * colonne ou une expression disponible dans le périmètre (« c.ue_id » si le
     * filtre joint ecs sous l'alias c, une semaine calculée sur e.date...).
     *
     * @return array<int|string, int>
     */
    public function expectedBy(Closure $eventFilter, string $cle): array
    {
        $query = $this->baseAttendus();

        $eventFilter($query);

        return $this->grouper($query, $cle, 'COUNT(*)');
    }

    /** Nombre d'étudiants distincts attendus sur le périmètre. */
    public function expectedStudents(Closure $eventFilter): int
    {
        $query = $this->baseAttendus();

        $eventFilter($query);

        return (int) $query->distinct()->count('ee.etudiant_id');
    }

    /**
     * Étudiants distincts attendus, regroupés comme expectedBy().
     *
     * @return array<int|string, int>
     */
    public function expectedStudentsBy(Closure $eventFilter, string $cle): array
    {
        $query = $this->baseAttendus();

        $eventFilter($query);

        return $this->grouper($query, $cle, 'COUNT(DISTINCT ee.etudiant_id)');
    }

    /**
     * Nombre de présences validées sur le même périmètre d'événements.
     *
     * @param  Closure(Builder):void  $eventFilter
     */
    public function recorded(Closure $eventFilter): int
    {
        $query = $this->basePresents();

        $eventFilter($query);

        return $query->count();
    }

    /**
     * Présences validées, regroupées comme expectedBy().
     *
     * @return array<int|string, int>
     */
    public function recordedBy(Closure $eventFilter, string $cle): array
    {
        $query = $this->basePresents();

        $eventFilter($query);

        return $this->grouper($query, $cle, 'COUNT(*)');
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

    /**
     * Absences de chaque étudiant sur le périmètre : les séances où il était
     * attendu (inscrit à l'EC) sans présence valide. Seuls les étudiants ayant
     * au moins une absence sont renvoyés, avec leur dernière séance manquée.
     *
     * @param  Closure(Builder):void  $eventFilter
     * @return array<string, array{attendus: int, presents: int, absences: int, dernier_manque: ?array{evenement_id: int, date: string, ec: ?string}}>  clés : identifiants (UUID) des étudiants
     */
    public function absencesParEtudiant(Closure $eventFilter): array
    {
        $comptes = $this->baseAttendusAvecPresence();

        $eventFilter($comptes);

        // DISTINCT : une présence en double ne compte pas deux fois.
        $lignes = $comptes
            ->selectRaw('ee.etudiant_id, COUNT(DISTINCT e.id) as attendus, COUNT(DISTINCT p.evenement_id) as presents')
            ->groupBy('ee.etudiant_id')
            ->havingRaw('COUNT(DISTINCT e.id) > COUNT(DISTINCT p.evenement_id)')
            ->get();

        if ($lignes->isEmpty()) {
            return [];
        }

        // Dernière séance manquée de chacun, en une seule requête.
        $manques = $this->baseAttendusAvecPresence()
            ->join('ecs as ec_manque', 'ec_manque.id', '=', 'e.ec_id');

        $eventFilter($manques);

        $derniers = $manques->whereNull('p.id')
            ->selectRaw('DISTINCT ON (ee.etudiant_id) ee.etudiant_id, e.id as evenement_id, e.date, ec_manque.intitule')
            ->orderBy('ee.etudiant_id')
            ->orderByDesc('e.date')
            ->orderByDesc('e.heure_debut')
            ->get()
            ->keyBy('etudiant_id');

        $resultat = [];

        foreach ($lignes as $ligne) {
            $attendus = (int) $ligne->attendus;
            $presents = (int) $ligne->presents;
            $dernier = $derniers->get($ligne->etudiant_id);

            // Identifiant UUID : surtout pas de conversion en entier.
            $resultat[$ligne->etudiant_id] = [
                'attendus'       => $attendus,
                'presents'       => $presents,
                'absences'       => $attendus - $presents,
                'dernier_manque' => $dernier ? [
                    'evenement_id' => (int) $dernier->evenement_id,
                    'date'         => substr((string) $dernier->date, 0, 10),
                    'ec'           => $dernier->intitule,
                ] : null,
            ];
        }

        return $resultat;
    }

    /** Présences attendues, chacune avec la présence valide qui l'honore (alias « p »), s'il y en a une. */
    private function baseAttendusAvecPresence(): Builder
    {
        return $this->baseAttendus()->leftJoin('presences as p', function ($join) {
            $join->on('p.evenement_id', '=', 'e.id')
                 ->on('p.etudiant_id', '=', 'ee.etudiant_id')
                 ->where('p.statut', '=', 'valide')
                 ->whereNull('p.deleted_at');
        });
    }

    private function baseAttendus(): Builder
    {
        return DB::table('evenements as e')
            ->join('etudiant_ec as ee', function ($join) {
                $join->on('ee.ec_id', '=', 'e.ec_id')
                     ->on('ee.annee_id', '=', 'e.annee_id');
            })
            ->join('etudiants as s', 's.id', '=', 'ee.etudiant_id')
            ->whereNull('e.deleted_at')
            ->whereNull('s.deleted_at')
            // Séance d'un groupe de TD ou de TP : seuls ses membres y sont attendus.
            ->where(function ($q) {
                $q->whereNull('e.groupe_id')
                  ->orWhereExists(fn ($membre) => $membre->select(DB::raw(1))->from('etudiant_groupe as eg')
                      ->whereColumn('eg.groupe_id', 'e.groupe_id')
                      ->whereColumn('eg.etudiant_id', 's.id'));
            });
    }

    private function basePresents(): Builder
    {
        // L'étudiant (alias « s », comme dans baseAttendus) : un filtre par filière
        // se lit sur lui, et vaut ainsi pour les deux membres du taux.
        return DB::table('presences as p')
            ->join('evenements as e', 'e.id', '=', 'p.evenement_id')
            ->join('etudiants as s', 's.id', '=', 'p.etudiant_id')
            ->whereNull('p.deleted_at')
            ->whereNull('e.deleted_at')
            ->where('p.statut', 'valide');
    }

    /** @return array<int|string, int> */
    private function grouper(Builder $query, string $cle, string $agregat): array
    {
        return $query->selectRaw("{$cle} as cle, {$agregat} as n")
            ->groupByRaw($cle)
            ->pluck('n', 'cle')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
