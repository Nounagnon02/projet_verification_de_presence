<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table des analyses IA (Gemini) asynchrones.
     * Conforme CDC 8.1 & 8.4 — job queue dédié avec retry.
     */
    public function up(): void
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'schedule' | 'courses'
            $table->string('status')->default('pending'); // pending | processing | completed | failed
            $table->string('file_path')->nullable();
            $table->json('result')->nullable();
            $table->float('score_de_confiance')->nullable();
            $table->string('statut_analyse')->nullable(); // 'valide' | 'a_reverifier'
            $table->text('warning')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};
