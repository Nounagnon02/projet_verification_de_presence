<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table pivot : inscription d'un étudiant à un EC (Élément Constitutif).
     * Conforme CDC 7.2.3 — Association automatique cours-étudiant.
     *
     * Permet de savoir précisément à quels cours chaque étudiant est inscrit,
     * plutôt que de se baser uniquement sur la filière.
     */
    public function up(): void
    {
        Schema::create('etudiant_ec', function (Blueprint $table) {
            $table->uuid('etudiant_id');
            $table->foreignId('ec_id')->constrained('ecs')->onDelete('cascade');
            $table->foreignId('annee_id')->constrained('annees_academiques')->onDelete('cascade');
            $table->timestamps();

            $table->primary(['etudiant_id', 'ec_id', 'annee_id']);

            $table->foreign('etudiant_id')
                  ->references('id')
                  ->on('etudiants')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etudiant_ec');
    }
};
