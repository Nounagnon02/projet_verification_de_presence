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
        Schema::create('presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->time('time')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'date']); // Éviter les doublons
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presences');
    }
};
