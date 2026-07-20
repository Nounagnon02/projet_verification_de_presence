<?php

namespace Tests\Unit;

use App\Models\Salle;
use Tests\TestCase;

class SalleDistanceTest extends TestCase
{
    /**
     * Test du calcul de distance Haversine.
     * Abomey-Calavi (UAC) → Cotonou : ~20 km
     */
    public function test_distance_metres(): void
    {
        $salle = new Salle();
        $salle->latitude = 6.4400;
        $salle->longitude = 2.3200;

        // Cotonou (environ)
        $distance = $salle->distanceMetres(6.3667, 2.4333);

        $this->assertNotNull($distance);
        $this->assertGreaterThan(10000, $distance); // > 10 km
        $this->assertLessThan(30000, $distance);    // < 30 km
    }

    /**
     * Test que la distance est nulle si la salle n'a pas de coordonnées.
     */
    public function test_distance_null_when_no_coordinates(): void
    {
        $salle = new Salle();

        $this->assertNull($salle->distanceMetres(6.36, 2.43));
    }

    /**
     * Test isWithinGeofence avec coordonnées identiques.
     */
    public function test_is_within_geofence_identical(): void
    {
        $salle = new Salle();
        $salle->latitude = 6.44;
        $salle->longitude = 2.32;
        $salle->rayon_geofence_m = 100;

        $this->assertTrue($salle->isWithinGeofence(6.44, 2.32));
    }

    /**
     * Test isWithinGeofence hors rayon.
     */
    public function test_is_within_geofence_outside(): void
    {
        $salle = new Salle();
        $salle->latitude = 6.44;
        $salle->longitude = 2.32;
        $salle->rayon_geofence_m = 10;

        // À ~100m — hors rayon
        $this->assertFalse($salle->isWithinGeofence(6.4405, 2.3210));
    }
}
