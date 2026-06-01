<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evenements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ec_id')->constrained('ecs')->onDelete('cascade');
            $table->foreignId('filiere_id')->constrained('filieres')->onDelete('cascade');
            $table->foreignId('annee_id')->constrained('annees_academiques')->onDelete('cascade');
            $table->date('date');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('salle')->nullable();
            $table->string('statut')->default('planifie'); // planifie, en_cours, termine, annule
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenements');
    }
};
