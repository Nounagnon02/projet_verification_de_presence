<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ue_id')->constrained('ues')->onDelete('cascade');
            $table->string('code')->unique();
            $table->string('intitule');
            $table->integer('volume_horaire')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecs');
    }
};
