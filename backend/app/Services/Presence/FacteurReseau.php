<?php

declare(strict_types=1);

namespace App\Services\Presence;

use App\Models\Salle;

/**
 * Facteur réseau : satisfait si le SSID ou le BSSID correspond, OU si l'IP du
 * client est dans la plage « ip_range » de la salle.
 *
 * Le second critère existait déjà en base et était calculé à chaque scan sans
 * jamais entrer dans la décision — un amphithéâtre câblé, où le SSID diffusé
 * varie d'une borne à l'autre, ne pouvait être validé que par le premier.
 *
 * L'IP n'est fiable que parce que le répartiteur de Render est déclaré proxy
 * de confiance (bootstrap/app.php) : sans cela, X-Forwarded-For est une entête
 * que n'importe quel client peut écrire.
 */
final class FacteurReseau
{
    /**
     * @return Verdict|null  null si la salle n'exige aucun facteur réseau.
     */
    public static function verifier(Salle $salle, ?string $ssid, ?string $bssid, ?string $ip): ?Verdict
    {
        if ($salle->hors_reseau) {
            return null;
        }

        $requis = (bool) ($salle->ssid_attendu || $salle->bssid_attendu || $salle->ip_range);
        if (!$requis) {
            return null;
        }

        // Correspondance réelle exigée : matchesWifi()/matchesIpRange() du
        // modèle renvoient « true » par défaut quand rien n'est configuré,
        // une réponse vide n'est donc jamais confondue avec une correspondance.
        $correspondWifi = (bool) ($salle->ssid_attendu || $salle->bssid_attendu)
            && $salle->matchesWifi($ssid, $bssid);
        $correspondIp = (bool) $salle->ip_range && $ip !== null
            && $salle->matchesIpRange($ip);

        if ($correspondWifi || $correspondIp) {
            return Verdict::satisfait([
                'wifi_valide' => $correspondWifi,
                'ip_valide'   => $correspondIp,
            ]);
        }

        $messageEtudiant = ($ssid || $bssid)
            ? 'Le réseau depuis lequel vous scannez n\'est pas reconnu.'
            : "Réseau non transmis : cette salle exige une validation depuis l'application mobile.";

        return Verdict::refus($messageEtudiant, 403, [
            'wifi_valide'   => false,
            'ip_valide'     => false,
            'ssid_recu'     => $ssid,
            'bssid_recu'    => $bssid,
            'ip_recue'      => $ip,
            'ssid_attendu'  => $salle->ssid_attendu,
            'bssid_attendu' => $salle->bssid_attendu,
            'ip_range'      => $salle->ip_range,
            'salle_id'      => $salle->id,
            'salle_nom'     => $salle->nom,
        ]);
    }
}
