<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Calendrier : périodes de semestre et fermetures.
 *
 * La génération ne regardait que le jour de la semaine : elle créait des
 * séances pendant les vacances, les examens, les jours fériés, et pour des
 * semestres qui n'avaient pas commencé. Ces séances fantômes consommaient le
 * volume des EC et comptaient tout le monde absent.
 *
 * - periodes_semestre : chaque établissement déclare, pour une année, la
 *   période des semestres impairs (S1, S3…) et celle des pairs (S2, S4…).
 *   Sans établissement : les filières qui n'en ont pas.
 * - fermetures : jours sans cours. Sans établissement : l'université entière
 *   (les jours fériés, déclarés par le super administrateur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodes_semestre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('annee_id')->constrained('annees_academiques')->cascadeOnDelete();
            $table->string('parite', 6);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->timestamps();
        });

        // Une période par parité, par établissement et par année ; null compte
        // comme un établissement.
        DB::statement('CREATE UNIQUE INDEX periodes_semestre_unique ON periodes_semestre (coalesce(etablissement_id, 0), annee_id, parite)');

        Schema::create('fermetures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('annee_id')->constrained('annees_academiques')->cascadeOnDelete();
            $table->string('type', 10);
            $table->string('libelle', 120);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->timestamps();
            $table->index(['date_debut', 'date_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fermetures');
        Schema::dropIfExists('periodes_semestre');
    }
};
