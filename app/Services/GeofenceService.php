<?php

namespace App\Services;

use App\Models\AttendanceSession;

class GeofenceService
{
    /**
     * Vérifie si la position du responsable est valide pour la session
     */
    public function isLocationValid(AttendanceSession $session, float $userLat, float $userLng): array
    {
        // Si la session n'a pas de géolocalisation définie, on accepte
        if (!$session->latitude || !$session->longitude) {
            return ['valid' => true, 'distance' => 0];
        }

        $distance = $this->calculateDistance(
            $session->latitude,
            $session->longitude,
            $userLat,
            $userLng
        );

        $isValid = $distance <= $session->radius;

        return [
            'valid' => $isValid,
            'distance' => round($distance),
            'radius' => $session->radius,
            'location_name' => $session->location_name
        ];
    }

    /**
     * Calcule la distance en mètres entre deux points (Formule Haversine)
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // Rayon de la terre en mètres

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
