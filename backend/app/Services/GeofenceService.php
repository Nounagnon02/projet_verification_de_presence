<?php

namespace App\Services;

use App\Models\QrCode;

class GeofenceService
{
    /**
     * Vérifie si la position de l'utilisateur est valide pour l'événement
     */
    public function isLocationValid(QrCode $qrCode, float $userLat, float $userLng): array
    {
        // Si l'événement n'a pas de géolocalisation définie, on accepte
        if (!$qrCode->latitude || !$qrCode->longitude) {
            return ['valid' => true, 'distance' => 0];
        }

        $distance = $this->calculateDistance(
            $qrCode->latitude,
            $qrCode->longitude,
            $userLat,
            $userLng
        );

        $isValid = $distance <= $qrCode->radius;

        return [
            'valid' => $isValid,
            'distance' => round($distance),
            'radius' => $qrCode->radius,
            'location_name' => $qrCode->location_name
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
