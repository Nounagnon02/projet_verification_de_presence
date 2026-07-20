<?php

namespace App\Services;

use App\Models\Salle;

/**
 * Service de géolocalisation — détermine si une position est valide
 * en la comparant aux salles configurées dans l'établissement.
 *
 * CONFORME CDC 7.4.2 : vérification de présence par géolocalisation.
 *
 * @see \App\Models\Salle::isWithinGeofence()
 */
class GeolocationService
{
    /**
     * Vérifie si les coordonnées sont valides pour une salle donnée.
     * Délègue au modèle Salle qui contient les coordonnées réelles.
     */
    public function isLocationValid(float $latitude, float $longitude, ?int $salleId = null): bool
    {
        if ($salleId) {
            $salle = Salle::find($salleId);
            if ($salle && $salle->actif && $salle->latitude !== null) {
                return $salle->isWithinGeofence($latitude, $longitude);
            }
        }

        // Aucune salle spécifique — vérifier si les coordonnées sont valides
        // (latitude entre -90 et 90, longitude entre -180 et 180)
        return $latitude >= -90 && $latitude <= 90
            && $longitude >= -180 && $longitude <= 180;
    }

    /**
     * Retourne les informations de localisation pour une salle.
     */
    public function getLocationInfo(float $latitude, float $longitude, ?int $salleId = null): array
    {
        $info = [
            'is_valid' => false,
            'nearest_location' => null,
        ];

        if ($salleId) {
            $salle = Salle::find($salleId);
            if ($salle && $salle->latitude !== null) {
                $info['is_valid'] = $salle->isWithinGeofence($latitude, $longitude);
                $info['nearest_location'] = [
                    'name'     => $salle->nom,
                    'latitude' => $salle->latitude,
                    'longitude' => $salle->longitude,
                    'radius'   => $salle->rayon_geofence_m,
                    'distance' => round($salle->distanceMetres($latitude, $longitude) ?? 0),
                ];
            }
        }

        return $info;
    }
}