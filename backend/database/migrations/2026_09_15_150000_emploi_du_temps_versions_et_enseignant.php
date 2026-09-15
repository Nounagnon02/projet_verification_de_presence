<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Emploi du temps : versions successives, enseignant, groupes.
 *
 * Un emploi du temps réel est daté et « susceptible de modifications » : la
 * version du 15 juin remplace celle de mars. Un créneau vaut donc sur une
 * période (valide_du, valide_au ; vide : sans borne). L'enseignant est gardé en
 * texte, pour l'affichage et le contrôle des chevauchements : l'entité
 * enseignant sort du cahier des charges.
 *
 * L'index unique (ec_id, jour_semaine, heure_debut) interdisait deux groupes de
 * TD du même cours à la même heure, et deux versions du même créneau. Il
 * compte désormais le groupe et le début de validité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emploi_du_temps', function (Blueprint $table) {
            $table->date('valide_du')->nullable();
            $table->date('valide_au')->nullable();
            $table->string('enseignant', 255)->nullable();
            $table->dropUnique('emploi_du_temps_ec_creneau_unique');
        });

        DB::statement("CREATE UNIQUE INDEX emploi_du_temps_creneau_unique ON emploi_du_temps (ec_id, jour_semaine, heure_debut, coalesce(groupe_id, 0), coalesce(valide_du, '1900-01-01'::date))");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS emploi_du_temps_creneau_unique');

        Schema::table('emploi_du_temps', function (Blueprint $table) {
            $table->unique(['ec_id', 'jour_semaine', 'heure_debut'], 'emploi_du_temps_ec_creneau_unique');
            $table->dropColumn(['valide_du', 'valide_au', 'enseignant']);
        });
    }
};
