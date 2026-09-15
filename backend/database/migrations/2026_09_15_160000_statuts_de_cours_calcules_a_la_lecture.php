<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le statut d'un EC ou d'une UE se calcule à la lecture (AvancementCours),
 * depuis les séances terminées. Les colonnes que recalculait chaque nuit
 * ecs:sync-statut disparaissent : planificateur arrêté, elles dérivaient en
 * silence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecs', fn (Blueprint $table) => $table->dropColumn('statut'));
        Schema::table('ues', fn (Blueprint $table) => $table->dropColumn('statut'));
    }

    public function down(): void
    {
        Schema::table('ecs', fn (Blueprint $table) => $table->string('statut')->default('non_demarre'));
        Schema::table('ues', fn (Blueprint $table) => $table->string('statut')->default('non_demarre'));
    }
};
