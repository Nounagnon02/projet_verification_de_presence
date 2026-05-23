<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('group')->nullable();
            $table->foreignId('users_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('rgpd_consent')->default(false);
            $table->timestamp('rgpd_consent_at')->nullable();
            $table->string('consent_method')->nullable();
            $table->integer('points')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
