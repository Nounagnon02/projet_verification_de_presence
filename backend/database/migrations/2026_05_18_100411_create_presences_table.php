<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presences', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('etudiant_id')->constrained('etudiants')->onDelete('cascade');
            $table->foreignId('evenement_id')->constrained('evenements')->onDelete('cascade');
            $table->timestamp('heure_scan');
            $table->string('device_fingerprint')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('statut')->default('valide'); // valide, suspect, rejete
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();

            $table->unique(['etudiant_id', 'evenement_id']); // CDC: un seul scan par étudiant par événement
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presences');
    }
};
