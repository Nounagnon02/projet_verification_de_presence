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
        Schema::create('device_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('device_fingerprint');
            $table->string('ip_address');
            $table->timestamp('last_verification');
            $table->timestamps();
            
            $table->index(['device_fingerprint', 'last_verification']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_verifications');
    }
};
