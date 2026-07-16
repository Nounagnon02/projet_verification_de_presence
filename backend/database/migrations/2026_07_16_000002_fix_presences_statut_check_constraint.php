<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Corrige la contrainte CHECK sur presences.statut.
     * La contrainte avait été créée avec les mauvaises valeurs
     * ('present', 'absent', 'retard', 'justifie' au lieu de
     * 'valide', 'rejete', 'suspect', 'en_attente', 'invalide').
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Supprimer l'ancienne contrainte invalide
        DB::statement('ALTER TABLE presences DROP CHECK presences_statut_check');

        // Ajouter la nouvelle contrainte avec les bonnes valeurs
        DB::statement("ALTER TABLE presences ADD CONSTRAINT presences_statut_check CHECK (statut IN ('valide', 'suspect', 'rejete', 'en_attente', 'invalide'))");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE presences DROP CHECK presences_statut_check');

        // Restaurer l'ancienne contrainte (valeurs incorrectes d'origine)
        DB::statement("ALTER TABLE presences ADD CONSTRAINT presences_statut_check CHECK (statut IN ('present', 'absent', 'retard', 'justifie'))");
    }
};
