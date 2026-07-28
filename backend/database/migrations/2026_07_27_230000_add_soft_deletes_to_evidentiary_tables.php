<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Active la suppression réversible (soft delete) sur les tables qui portent
 * une valeur de preuve.
 *
 * Sur un système de présence, effacer définitivement un étudiant, un
 * événement ou une présence est difficilement acceptable : ces données
 * peuvent être invoquées en cas de litige. On ajoute une colonne deleted_at
 * (ajout non destructif) pour que les suppressions deviennent réversibles.
 */
return new class extends Migration
{
    private array $tables = ['etudiants', 'evenements', 'presences'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropSoftDeletes();
                });
            }
        }
    }
};
