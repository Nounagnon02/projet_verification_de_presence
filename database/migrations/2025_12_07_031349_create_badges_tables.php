<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Table des badges
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->default('🏅');
            $table->text('description');
            $table->string('condition'); // ex: 'streak_7', 'perfect_month', 'early_bird'
            $table->integer('threshold')->default(1); // Valeur nécessaire pour débloquer
            $table->integer('points')->default(10);
            $table->string('color')->default('blue');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Table de liaison membre-badges
        Schema::create('member_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->foreignId('badge_id')->constrained()->onDelete('cascade');
            $table->timestamp('earned_at')->useCurrent();
            $table->json('metadata')->nullable(); // Pour stocker des infos contextuelles
            $table->timestamps();
            
            $table->unique(['member_id', 'badge_id']); // Un badge par membre
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_badges');
        Schema::dropIfExists('badges');
    }
};

