<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Langue choisie par le responsable, pour que la préférence suive
            // le compte d'un appareil à l'autre. null = aucune préférence,
            // SetLocale retombe alors sur la session, le cookie, puis le
            // navigateur. 12 caractères : « fon » (ISO 639-3) comme « yo_BJ ».
            $table->string('locale', 12)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
