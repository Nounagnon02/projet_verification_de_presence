<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ues', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('intitule');
            $table->foreignId('filiere_id')->constrained('filieres')->onDelete('cascade');
            $table->foreignId('annee_id')->constrained('annees_academiques')->onDelete('cascade');
            $table->integer('semestre');
            $table->integer('volume_horaire');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ues');
    }
};
