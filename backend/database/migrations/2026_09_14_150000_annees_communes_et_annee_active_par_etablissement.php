<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Années académiques communes à l'université, année active par établissement.
 *
 * Chaque faculté créait ses propres années : dix facultés auraient eu dix
 * « 2025-2026 », que le libellé, unique dans toute la base, interdisait
 * d'ailleurs à partir de la deuxième. Les années sont désormais créées par le
 * super administrateur et partagées.
 *
 * Les facultés ne basculent pas toutes le même jour : l'une finit encore
 * 2024-2025 quand une autre a commencé 2025-2026. Chacune garde donc SON année
 * active (etablissements.annee_active_id). Le drapeau « active » de l'année
 * devient l'année en cours de l'université, que suivent les établissements qui
 * n'ont rien choisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->foreignId('annee_active_id')->nullable()->constrained('annees_academiques')->nullOnDelete();
        });

        // Chaque établissement garde l'année qu'il avait activée.
        foreach (DB::table('annees_academiques')->where('active', true)->whereNotNull('etablissement_id')->get() as $annee) {
            DB::table('etablissements')->where('id', $annee->etablissement_id)->update(['annee_active_id' => $annee->id]);
        }

        // Une seule année en cours pour l'université : celle dont les dates
        // contiennent aujourd'hui, à défaut la plus récente des actives.
        $actives = DB::table('annees_academiques')->where('active', true)->orderByDesc('date_debut')->get();

        if ($actives->count() > 1) {
            $aujourdhui = now()->toDateString();
            $retenue = $actives->first(fn ($a) => $a->date_debut <= $aujourdhui && $a->date_fin >= $aujourdhui) ?? $actives->first();

            DB::table('annees_academiques')->where('active', true)->where('id', '!=', $retenue->id)->update(['active' => false]);
        }

        // Les années deviennent communes à l'université.
        DB::table('annees_academiques')->update(['etablissement_id' => null]);
    }

    public function down(): void
    {
        // Chaque année revient à l'établissement qui l'avait activée ; les autres restent communes.
        foreach (DB::table('etablissements')->whereNotNull('annee_active_id')->get(['id', 'annee_active_id']) as $etablissement) {
            DB::table('annees_academiques')->where('id', $etablissement->annee_active_id)->update(['etablissement_id' => $etablissement->id]);
        }

        Schema::table('etablissements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('annee_active_id');
        });
    }
};
