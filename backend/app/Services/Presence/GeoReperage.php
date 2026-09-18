<?php

declare(strict_types=1);

namespace App\Services\Presence;

use App\Models\Salle;

/**
 * Facteur de géolocalisation.
 *
 * Le message destiné à l'étudiant reste générique : le refus annonçait
 * auparavant la distance exacte à la salle et le rayon autorisé, et trois
 * scans depuis trois positions suffisaient à trianguler la salle. Le détail
 * — distance, rayon, salle visée — ne sort plus que dans « detail », à
 * l'usage exclusif de l'anomalie enregistrée par l'administration.
 */
final class GeoReperage
{
    /**
     * @return Verdict|null  null si la salle n'exige pas de géolocalisation.
     */
    public static function verifier(Salle $salle, ?float $latitude, ?float $longitude): ?Verdict
    {
        if ($salle->latitude === null || $salle->longitude === null) {
            return null;
        }

        $distance = $salle->distanceMetres($latitude, $longitude);

        if ($distance !== null && $distance <= $salle->rayon_geofence_m) {
            return Verdict::satisfait(['gps_valide' => true, 'distance_metres' => $distance]);
        }

        // Distinguer « position absente » de « position hors zone » : le
        // premier cas oriente vers l'autorisation de géolocalisation, sans
        // rien divulguer sur la salle.
        $messageEtudiant = $distance === null
            ? 'Position non transmise : autorisez la géolocalisation, puis réessayez.'
            : 'Vous ne semblez pas être dans la salle du cours.';

        return Verdict::refus($messageEtudiant, 403, [
            'gps_valide'      => false,
            'distance_metres' => $distance,
            'rayon_m'         => $salle->rayon_geofence_m,
            'salle_id'        => $salle->id,
            'salle_nom'       => $salle->nom,
        ]);
    }
}
