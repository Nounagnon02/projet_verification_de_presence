<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un code d'UE, comme un code d'EC, est unique dans une année et un
 * établissement, et se réutilise d'une année à l'autre.
 *
 * La base disait « unique par filière » pour les UE et « unique par UE » pour
 * les EC ; le formulaire, lui, exigeait un code d'EC unique dans toute la base.
 * Un EC reconduit sur l'année suivante devenait impossible à modifier, et deux
 * filières d'une même faculté pouvaient porter la même année deux UE de même
 * code sans que rien ne le signale.
 *
 * L'établissement et l'année sont recopiés sur les UE et les EC (maintenus par
 * les modèles) pour que la base elle-même porte la règle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ues', function (Blueprint $table) {
            $table->foreignId('etablissement_id')->nullable()->after('filiere_id')->constrained('etablissements')->nullOnDelete();
        });

        Schema::table('ecs', function (Blueprint $table) {
            $table->foreignId('annee_id')->nullable()->after('ue_id')->constrained('annees_academiques')->cascadeOnDelete();
            $table->foreignId('etablissement_id')->nullable()->after('annee_id')->constrained('etablissements')->nullOnDelete();
        });

        DB::statement('update ues set etablissement_id = f.etablissement_id from filieres f where f.id = ues.filiere_id');
        DB::statement('update ecs set annee_id = u.annee_id, etablissement_id = u.etablissement_id from ues u where u.id = ecs.ue_id');
        DB::statement('alter table ecs alter column annee_id set not null');

        Schema::table('ues', fn (Blueprint $table) => $table->dropUnique('ues_code_filiere_annee_unique'));
        Schema::table('ecs', fn (Blueprint $table) => $table->dropUnique('ecs_code_ue_unique'));

        // Casse ignorée ; une filière sans établissement compte comme un seul
        // établissement, pour que la règle vaille aussi pour elle.
        DB::statement('create unique index ues_code_par_annee_unique on ues (coalesce(etablissement_id, 0), annee_id, lower(code))');
        DB::statement('create unique index ecs_code_par_annee_unique on ecs (coalesce(etablissement_id, 0), annee_id, lower(code))');
    }

    public function down(): void
    {
        DB::statement('drop index if exists ues_code_par_annee_unique');
        DB::statement('drop index if exists ecs_code_par_annee_unique');

        Schema::table('ues', fn (Blueprint $table) => $table->unique(['code', 'filiere_id', 'annee_id'], 'ues_code_filiere_annee_unique'));
        Schema::table('ecs', fn (Blueprint $table) => $table->unique(['code', 'ue_id'], 'ecs_code_ue_unique'));

        Schema::table('ecs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('etablissement_id');
            $table->dropConstrainedForeignId('annee_id');
        });

        Schema::table('ues', fn (Blueprint $table) => $table->dropConstrainedForeignId('etablissement_id'));
    }
};
