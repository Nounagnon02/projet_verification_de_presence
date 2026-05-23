<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('device_fingerprint')->unique();
            $table->string('ip_address')->nullable();
            $table->timestamp('last_verification')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_verifications');
    }
};
