<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Groupes de TD et de TP par promotion (filière et année).
 *
 * Chaque séance de TD attendait toute la filière : l'étudiant du groupe B était
 * compté absent au TD du groupe A. Une séance, comme un créneau d'emploi du
 * temps, peut désormais viser un groupe ; sans groupe, toute la promotion.
 *
 * Un étudiant appartient à un groupe de TD et à un groupe de TP au plus pour une
 * année : l'année et le type sont recopiés sur l'appartenance, et la base
 * l'impose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groupes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filiere_id')->constrained('filieres')->cascadeOnDelete();
            $table->foreignId('annee_id')->constrained('annees_academiques')->cascadeOnDelete();
            $table->string('type', 2);
            $table->string('libelle', 30);
            $table->timestamps();
            $table->unique(['filiere_id', 'annee_id', 'type', 'libelle']);
        });

        Schema::create('etudiant_groupe', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->foreignId('groupe_id')->constrained('groupes')->cascadeOnDelete();
            $table->foreignId('annee_id')->constrained('annees_academiques')->cascadeOnDelete();
            $table->string('type', 2);
            $table->timestamps();
            $table->unique(['etudiant_id', 'annee_id', 'type']);
        });

        Schema::table('evenements', function (Blueprint $table) {
            $table->foreignId('groupe_id')->nullable()->after('type_cours')->constrained('groupes')->nullOnDelete();
        });

        Schema::table('emploi_du_temps', function (Blueprint $table) {
            $table->foreignId('groupe_id')->nullable()->after('type_cours')->constrained('groupes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('emploi_du_temps', fn (Blueprint $table) => $table->dropConstrainedForeignId('groupe_id'));
        Schema::table('evenements', fn (Blueprint $table) => $table->dropConstrainedForeignId('groupe_id'));
        Schema::dropIfExists('etudiant_groupe');
        Schema::dropIfExists('groupes');
    }
};
