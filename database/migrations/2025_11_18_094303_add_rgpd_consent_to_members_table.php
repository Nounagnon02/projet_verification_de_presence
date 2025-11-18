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
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('rgpd_consent')->default(false);
            $table->timestamp('rgpd_consent_at')->nullable();
            $table->string('consent_method')->default('oral'); // oral, written, digital
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['rgpd_consent', 'rgpd_consent_at', 'consent_method']);
        });
    }
};
