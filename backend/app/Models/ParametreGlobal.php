<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Réglages anti-fraude et de scan de l'université, modifiables par le super
 * administrateur (écran « Paramètres »). Une seule ligne existe jamais, créée
 * par la migration : actuel() la renvoie sans jamais en chercher une autre.
 *
 * Avant cette table, ces valeurs vivaient dans config/presence.php (variables
 * d'environnement, changement impossible sans redéploiement) : Evenement,
 * RegleSeanceService, EcController, StudentQrCodeController et
 * AppServiceProvider (limiteurs de débit) les lisent désormais d'ici.
 */
class ParametreGlobal extends Model
{
    protected $table = 'parametres_globaux';

    protected $fillable = [
        'scan_minutes_avant_fin',
        'scan_minutes_apres_fin',
        'qr_ttl_secondes',
        'qr_visible_delegue_avant_fin',
        'seance_duree_max_heures',
        'limite_scan_par_etudiant',
        'limite_scan_par_ip',
    ];

    protected $casts = [
        'scan_minutes_avant_fin' => 'integer',
        'scan_minutes_apres_fin' => 'integer',
        'qr_ttl_secondes' => 'integer',
        'qr_visible_delegue_avant_fin' => 'integer',
        'seance_duree_max_heures' => 'float',
        'limite_scan_par_etudiant' => 'integer',
        'limite_scan_par_ip' => 'integer',
    ];

    /**
     * La ligne unique. Jamais mise en cache : ces valeurs conditionnent
     * l'anti-fraude et sont modifiées rarement — la fraîcheur prime sur
     * l'économie d'une requête sur une table à une seule ligne.
     */
    public static function actuel(): self
    {
        return static::firstOrFail();
    }
}
