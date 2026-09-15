<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Volumes d'un EC par type de cours, et type de chaque séance.
 *
 * Un EC n'avait qu'un volume, et une séance de TD consommait le budget du cours
 * magistral. Les maquettes de l'UAC donnent les heures par type (Cours, TP/TD —
 * parfois regroupés en une colonne, d'où la réserve TP/TD).
 *
 * Les EC existants sont marqués « à ventiler » : leur total reste la référence
 * tant que personne n'a réparti leurs heures. Rien n'est perdu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecs', function (Blueprint $table) {
            $table->unsignedSmallInteger('volume_cm')->default(0)->after('volume_horaire');
            $table->unsignedSmallInteger('volume_td')->default(0)->after('volume_cm');
            $table->unsignedSmallInteger('volume_tp')->default(0)->after('volume_td');
            $table->unsignedSmallInteger('volume_td_tp')->default(0)->after('volume_tp');
            $table->boolean('volume_a_ventiler')->default(false)->after('volume_td_tp');
        });

        DB::table('ecs')->update(['volume_a_ventiler' => true]);

        Schema::table('ues', function (Blueprint $table) {
            $table->unsignedTinyInteger('credits')->nullable()->after('volume_horaire');
        });

        Schema::table('evenements', function (Blueprint $table) {
            $table->string('type_cours', 12)->default('cm')->after('ec_id');
        });

        // « cours », « CM », « TD »… : une seule écriture, celle de TypeCours.
        DB::statement("update emploi_du_temps set type_cours = case when lower(type_cours) in ('td', 'tp', 'evaluation') then lower(type_cours) else 'cm' end");
    }

    public function down(): void
    {
        DB::statement("update emploi_du_temps set type_cours = 'cours' where type_cours = 'cm'");

        Schema::table('evenements', fn (Blueprint $table) => $table->dropColumn('type_cours'));
        Schema::table('ues', fn (Blueprint $table) => $table->dropColumn('credits'));
        Schema::table('ecs', fn (Blueprint $table) => $table->dropColumn(['volume_cm', 'volume_td', 'volume_tp', 'volume_td_tp', 'volume_a_ventiler']));
    }
};
