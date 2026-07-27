<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index manquants sur les colonnes de filtrage et de jointure les plus
 * sollicitées.
 *
 * `evenements` n'avait aucun index en dehors de sa clé primaire, alors que
 * le tableau de bord filtre dessus par date et par statut à chaque appel.
 * `etudiants` n'avait pas d'index sur filiere_id / annee_id, utilisés par
 * le cloisonnement par établissement et par tous les filtres de liste.
 */
return new class extends Migration
{
    /**
     * Chaque index est créé séparément et ignoré s'il existe déjà : la base
     * de production a été migrée à la main plusieurs fois, on ne peut pas
     * supposer un état de départ homogène.
     */
    private array $indexes = [
        'evenements'  => [
            'evenements_date_index'            => ['date'],
            'evenements_date_statut_index'     => ['date', 'statut'],
            'evenements_filiere_id_index'      => ['filiere_id'],
            'evenements_ec_id_index'           => ['ec_id'],
            'evenements_annee_id_index'        => ['annee_id'],
        ],
        'etudiants'   => [
            'etudiants_filiere_id_index'       => ['filiere_id'],
            'etudiants_annee_id_index'         => ['annee_id'],
        ],
        'presences'   => [
            'presences_heure_scan_index'       => ['heure_scan'],
            'presences_statut_index'           => ['statut'],
        ],
        'etudiant_ec' => [
            // La clé primaire est (etudiant_id, ec_id, annee_id) : elle ne
            // couvre pas les recherches partant de l'EC.
            'etudiant_ec_ec_id_index'          => ['ec_id'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $definitions) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($definitions as $name => $columns) {
                if ($this->indexExists($name)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $t) use ($columns, $name) {
                    $t->index($columns, $name);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $definitions) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($definitions) as $name) {
                if (!$this->indexExists($name)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $t) use ($name) {
                    $t->dropIndex($name);
                });
            }
        }
    }

    private function indexExists(string $name): bool
    {
        return count(\Illuminate\Support\Facades\DB::select(
            'select 1 from pg_indexes where schemaname = current_schema() and indexname = ?',
            [$name]
        )) > 0;
    }
};
