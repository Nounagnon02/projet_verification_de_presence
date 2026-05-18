<?php

namespace App\Services;

class GeolocationService
{
    // Coordonnées autorisées (exemple: bureau principal)
    private const ALLOWED_LOCATIONS = [
        [
            'name' => 'Bureau Principal',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'radius' => 100 // mètres
        ]
    ];

    public function isLocationValid(float $latitude, float $longitude): bool
    {
        foreach (self::ALLOWED_LOCATIONS as $location) {
            $distance = $this->calculateDistance(
                $latitude, 
                $longitude, 
                $location['latitude'], 
                $location['longitude']
            );
            
            if ($distance <= $location['radius']) {
                return true;
            }
        }
        
        return false;
    }

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // mètres
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) + 
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLon/2) * sin($dLon/2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }

    public function getLocationInfo(float $latitude, float $longitude): array
    {
        return [
            'is_valid' => $this->isLocationValid($latitude, $longitude),
            'nearest_location' => $this->getNearestLocation($latitude, $longitude)
        ];
    }

    private function getNearestLocation(float $latitude, float $longitude): ?array
    {
        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach (self::ALLOWED_LOCATIONS as $location) {
            $distance = $this->calculateDistance(
                $latitude, 
                $longitude, 
                $location['latitude'], 
                $location['longitude']
            );
            
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = array_merge($location, ['distance' => round($distance)]);
            }
        }

        return $nearest;
    }
}