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
        Schema::create('anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->string('type'); // 'multiple_devices', 'suspicious_location', 'unusual_time'
            $table->text('description');
            $table->string('severity')->default('medium'); // 'low', 'medium', 'high', 'critical'
            $table->json('metadata')->nullable(); // Données contextuelles (IP, coords, etc.)
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anomalies');
    }
};
