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
        Schema::table('ecs', function (Blueprint $table) {
            $table->string('statut', 20)->default('non_demarre')->after('volume_horaire');
        });

        Schema::table('ues', function (Blueprint $table) {
            $table->string('statut', 20)->default('non_demarre')->after('volume_horaire');
        });
    }

    public function down(): void
    {
        Schema::table('ecs', function (Blueprint $table) {
            $table->dropColumn('statut');
        });

        Schema::table('ues', function (Blueprint $table) {
            $table->dropColumn('statut');
        });
    }
};
