<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages anti-fraude et de scan, jusqu'ici figés par variables
 * d'environnement dans config/presence.php (supprimé par cette migration) :
 * le super administrateur les modifie désormais depuis l'écran « Paramètres »,
 * sans redéploiement.
 *
 * Une seule ligne existe jamais, créée ci-dessous à partir des valeurs
 * actuelles de chaque environnement (son .env, ou leur défaut) : la bascule
 * ne change le comportement de personne au moment où elle s'applique.
 *
 * `env()` est lu ici directement, et non via config('presence...'), parce que
 * cette migration continue d'exister après la suppression du fichier de
 * configuration qui portait ces clés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametres_globaux', function (Blueprint $table) {
            $table->id();

            // Fenêtre de prise de présence, en minutes autour de l'heure de FIN
            // du cours (pas son début : un scan dès l'ouverture laisserait
            // valider une présence avant de repartir).
            $table->unsignedSmallInteger('scan_minutes_avant_fin');
            $table->unsignedSmallInteger('scan_minutes_apres_fin');

            // Durée de vie d'un jeton QR avant rotation, en secondes. En
            // dessous de 60 s, sans effet réel : le planificateur qui fait
            // tourner le jeton ne s'exécute qu'une fois par minute.
            $table->unsignedSmallInteger('qr_ttl_secondes');

            // Minutes avant la fin où le délégué voit le QR dans l'app mobile.
            $table->unsignedSmallInteger('qr_visible_delegue_avant_fin');

            // Plafond de durée d'UN créneau (heures), distinct du volume
            // horaire total de l'EC.
            $table->decimal('seance_duree_max_heures', 4, 1);

            // Débit de scan par minute. Par étudiant : la règle anti-fraude
            // (CDC 9.2.4). Par IP : un filet anti-inondation, pas une règle de
            // fraude — il doit rester très au-dessus de l'hypothèse H3 (500
            // scans simultanés) : une salle ou un campus derrière un même NAT
            // partagent une adresse, et un plafond trop bas y a déjà rejeté
            // des scans légitimes (mesuré à 600).
            $table->unsignedSmallInteger('limite_scan_par_etudiant');
            $table->unsignedInteger('limite_scan_par_ip');

            $table->timestamps();
        });

        DB::table('parametres_globaux')->insert([
            'scan_minutes_avant_fin' => (int) env('PRESENCE_SCAN_AVANT_FIN', 15),
            'scan_minutes_apres_fin' => (int) env('PRESENCE_SCAN_APRES_FIN', 10),
            'qr_ttl_secondes' => (int) env('PRESENCE_QR_TTL', 60),
            'qr_visible_delegue_avant_fin' => (int) env('PRESENCE_QR_DELEGUE_AVANT_FIN', 10),
            'seance_duree_max_heures' => (float) env('PRESENCE_SEANCE_DUREE_MAX', 5),
            'limite_scan_par_etudiant' => (int) env('PRESENCE_SCAN_MAX_PAR_ETUDIANT', 3),
            'limite_scan_par_ip' => (int) env('PRESENCE_SCAN_MAX_PAR_IP', 2000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres_globaux');
    }
};
