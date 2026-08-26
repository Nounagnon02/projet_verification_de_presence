<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le code d'une UE est unique DANS sa filière et son année, pas dans tout le
 * système.
 *
 * La contrainte posée jusqu'ici, « ues_code_unique » sur la seule colonne code,
 * contredisait le modèle métier et le code qui l'exploite :
 *
 *  - CsvImportController cherche une UE existante par (code, filiere_id,
 *    annee_id). Il tenait donc déjà le code pour unique dans ce triplet.
 *
 *  - Une maquette se reconduit d'une année sur l'autre : MTH1321 existe en
 *    2024-2025 ET en 2025-2026. Avec l'ancienne contrainte, la seconde année
 *    était impossible — l'insertion partait en SQLSTATE 23505, remonté brut à
 *    l'utilisateur en erreur 500.
 *
 *  - Deux filières peuvent légitimement partager un code de tronc commun.
 *
 * Même raisonnement pour les ECs : leur code est unique dans leur UE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ues', function (Blueprint $table) {
            $table->dropUnique('ues_code_unique');
            $table->unique(['code', 'filiere_id', 'annee_id'], 'ues_code_filiere_annee_unique');
        });

        Schema::table('ecs', function (Blueprint $table) {
            $table->dropUnique('ecs_code_unique');
            $table->unique(['code', 'ue_id'], 'ecs_code_ue_unique');
        });
    }

    public function down(): void
    {
        // Le retour en arrière n'est possible que si aucun code n'a été réutilisé
        // entre-temps. On le tente, et l'échec éventuel est explicite plutôt que
        // silencieux.
        Schema::table('ues', function (Blueprint $table) {
            $table->dropUnique('ues_code_filiere_annee_unique');
            $table->unique('code', 'ues_code_unique');
        });

        Schema::table('ecs', function (Blueprint $table) {
            $table->dropUnique('ecs_code_ue_unique');
            $table->unique('code', 'ecs_code_unique');
        });
    }
};
