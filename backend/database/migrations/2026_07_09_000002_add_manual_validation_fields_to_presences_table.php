<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete()->after('longitude');
            $table->timestamp('validated_at')->nullable()->after('validated_by');
            $table->text('validation_motif')->nullable()->after('validated_at');
        });
    }

    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
            $table->dropColumn(['validated_by', 'validated_at', 'validation_motif']);
        });
    }
};