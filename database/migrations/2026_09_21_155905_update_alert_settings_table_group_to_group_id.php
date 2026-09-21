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
        Schema::table('alert_settings', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'group']);
            $table->dropColumn('group');
            $table->foreignId('group_id')->after('user_id')->constrained()->onDelete('cascade');
            $table->unique(['user_id', 'group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alert_settings', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'group_id']);
            $table->dropConstrainedForeignId('group_id');
            $table->string('group')->after('user_id');
            $table->unique(['user_id', 'group']);
        });
    }
};
