<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('faculte_admin')->after('group');
            $table->foreignId('etablissement_id')->nullable()->after('role')
                ->constrained('etablissements')->nullOnDelete();
            $table->string('telephone')->nullable()->after('email');
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['etablissement_id']);
            $table->dropColumn(['role', 'etablissement_id', 'telephone', 'must_change_password']);
        });
    }
};
