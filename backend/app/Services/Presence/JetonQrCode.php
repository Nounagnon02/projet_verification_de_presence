<?php

declare(strict_types=1);

namespace App\Services\Presence;

use App\Models\QrCode;

/**
 * Facteur visuel : le jeton porté par le QR Code affiché en salle.
 *
 * Le jeton n'est PAS à usage unique, et ce n'est pas un oubli. Il l'a été, et
 * la conséquence, mesurée, était qu'un seul étudiant pouvait valider par jeton :
 * les suivants recevaient 410 jusqu'à la rotation, qui a lieu chaque minute. Un
 * amphithéâtre de 500 étudiants aurait demandé plus de huit heures. Ce qui rend
 * un code photographié puis partagé inexploitable, c'est sa DURÉE DE VIE de
 * soixante secondes, pas le nombre de fois qu'il sert ; la double validation,
 * elle, est arrêtée par la contrainte d'unicité (etudiant_id, evenement_id).
 */
class JetonQrCode
{
    /**
     * Message unique pour « jeton inconnu », « jeton désactivé » et « jeton
     * expiré » : les distinguer apprendrait à un scanneur automatique quels
     * jetons existent.
     */
    public const REFUS = 'QR Code expiré ou invalide. Veuillez rescanner.';

    /**
     * Le QR Code actif et non expiré désigné par ce jeton, ou null s'il n'y en
     * a aucun — auquel cas le scan est refusé par un 410.
     *
     * Les relations lues plus loin dans le scan sont chargées ici : sans ce
     * chargement anticipé, l'événement, sa salle et son EC coûtent trois
     * allers-retours supplémentaires à CHAQUE prise de présence.
     */
    public function resoudre(string $jeton): ?QrCode
    {
        $qrCode = QrCode::with(['evenement.salleRef', 'evenement.ec'])
            ->where('token', $jeton)
            ->where('actif', true)
            ->first();

        if (!$qrCode || $qrCode->isExpired() || !$qrCode->evenement) {
            return null;
        }

        return $qrCode;
    }
}
