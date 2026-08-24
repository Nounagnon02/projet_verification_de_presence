<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index servant la détection d'appareil partagé (buddy punching).
 *
 * PresenceController exécute à CHAQUE scan :
 *
 *   select count(distinct etudiant_id) from presences
 *    where evenement_id = ? and device_fingerprint = ? and etudiant_id != ?
 *
 * Aucun index ne couvrait ce couple. Le plus proche,
 * presences_evenement_heure_index, porte sur (evenement_id, heure_scan) : il
 * permet de restreindre à l'événement, mais device_fingerprint reste filtré
 * ligne à ligne. Sur un amphi de 500 présences, cela signifie 500 comparaisons
 * de chaîne à chaque nouveau scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->index(
                ['evenement_id', 'device_fingerprint'],
                'presences_evenement_appareil_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dropIndex('presences_evenement_appareil_index');
        });
    }
};
