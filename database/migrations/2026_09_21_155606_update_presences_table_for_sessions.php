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
        Schema::table('presences', function (Blueprint $table) {
            $table->dropUnique(['member_id', 'date']);

            $table->foreignId('attendance_session_id')->nullable()->after('member_id')->constrained()->onDelete('cascade');
            $table->foreignId('scanned_by')->nullable()->after('attendance_session_id')->constrained('users')->onDelete('set null');
            $table->string('verification_method')->default('manual')->after('time');

            $table->unique(['member_id', 'attendance_session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dropUnique(['member_id', 'attendance_session_id']);
            $table->dropConstrainedForeignId('attendance_session_id');
            $table->dropConstrainedForeignId('scanned_by');
            $table->dropColumn('verification_method');

            $table->unique(['member_id', 'date']);
        });
    }
};
