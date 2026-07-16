<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table pivot : association entre filières et années académiques.
     * Permet de lier explicitement les filières aux années (une filière peut
     * être active ou non selon l'année).
     */
    public function up(): void
    {
        Schema::create('filiere_annee', function (Blueprint $table) {
            $table->foreignId('filiere_id')->constrained('filieres')->onDelete('cascade');
            $table->foreignId('annee_id')->constrained('annees_academiques')->onDelete('cascade');
            $table->timestamps();

            $table->primary(['filiere_id', 'annee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filiere_annee');
    }
};
