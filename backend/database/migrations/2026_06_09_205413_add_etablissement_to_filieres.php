<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filieres', function (Blueprint $table) {
            $table->foreignId('etablissement_id')->nullable()->after('niveau')
                ->constrained('etablissements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('filieres', function (Blueprint $table) {
            $table->dropForeign(['etablissement_id']);
            $table->dropColumn('etablissement_id');
        });
    }
};
