<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ICON_KEYS = [
        "\u{1F389}" => 'sparkle',
        "\u{1F525}" => 'flame',
        "\u{1F4AA}" => 'bolt',
        "\u{1F3C6}" => 'trophy',
        "\u{2B50}" => 'star',
        "\u{2B50}\u{FE0F}" => 'star',
        "\u{1F305}" => 'sunrise',
        "\u{1F4C8}" => 'trend',
        "\u{1F4CA}" => 'chart',
        "\u{1F3AF}" => 'target',
        "\u{1F4AF}" => 'award',
        "\u{1F3C5}" => 'award',
    ];

    public function up(): void
    {
        foreach (self::ICON_KEYS as $emoji => $key) {
            DB::table('badges')->where('icon', $emoji)->update(['icon' => $key]);
        }

        Schema::table('badges', function (Blueprint $table) {
            $table->string('icon')->default('award')->change();
        });
    }

    public function down(): void
    {
        // Les emojis d'origine ne sont volontairement pas restaurés.
    }
};
