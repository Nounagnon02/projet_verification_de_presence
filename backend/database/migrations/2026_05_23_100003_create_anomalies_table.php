<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignUuid('etudiant_id')->nullable()->constrained('etudiants')->nullOnDelete();
            $table->string('type');                  // double_scan, device_mismatch, hors_délai, etc.
            $table->text('description')->nullable();
            $table->string('severity')->default('low'); // low, medium, high, critical
            $table->json('metadata')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomalies');
    }
};
