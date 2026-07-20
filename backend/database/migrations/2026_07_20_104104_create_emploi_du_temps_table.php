<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emploi_du_temps', function (Blueprint $table) {
            $table->id();

            // EC concerné
            $table->foreignId('ec_id')->constrained('ecs')->onDelete('cascade');
            $table->foreignId('filiere_id')->constrained('filieres')->onDelete('cascade');
            $table->foreignId('annee_id')->constrained('annees_academiques')->onDelete('cascade');

            // Créneau hebdomadaire
            $table->unsignedTinyInteger('jour_semaine'); // 1=Lundi … 7=Dimanche
            $table->time('heure_debut');
            $table->time('heure_fin');

            // Salle
            $table->foreignId('salle_id')->nullable()->constrained('salles')->nullOnDelete();
            $table->string('salle_libelle')->nullable(); // fallback texte libre

            // Type de cours
            $table->string('type_cours')->default('cours'); // cours, td, tp

            $table->timestamps();

            // Un EC ne peut pas avoir deux créneaux au même moment
            $table->unique(['ec_id', 'jour_semaine', 'heure_debut'], 'emploi_du_temps_ec_creneau_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emploi_du_temps');
    }
};
