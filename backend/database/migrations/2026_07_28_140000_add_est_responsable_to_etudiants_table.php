<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Désignation de l'étudiant responsable (délégué) de sa promotion.
 *
 * Un simple drapeau suffit : un étudiant appartient à exactement une filière et
 * une année, donc « délégué » se rapporte nécessairement à sa propre promotion.
 * Une table de liaison ne se justifierait que pour historiser les mandats.
 *
 * Pas de contrainte d'unicité par promotion : une promotion a souvent un délégué
 * et un adjoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etudiants', function (Blueprint $table) {
            $table->boolean('est_responsable')->default(false)->after('identifiant_unique');
        });

        // La recherche du délégué se fait toujours par promotion.
        Schema::table('etudiants', function (Blueprint $table) {
            $table->index(['filiere_id', 'annee_id', 'est_responsable'], 'etudiants_delegue_index');
        });
    }

    public function down(): void
    {
        Schema::table('etudiants', function (Blueprint $table) {
            $table->dropIndex('etudiants_delegue_index');
            $table->dropColumn('est_responsable');
        });
    }
};
