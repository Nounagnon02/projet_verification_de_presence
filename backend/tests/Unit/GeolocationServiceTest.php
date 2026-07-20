<?php

namespace Tests\Unit;

use App\Services\GeolocationService;
use Tests\TestCase;

class GeolocationServiceTest extends TestCase
{
    private GeolocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GeolocationService();
    }

    /**
     * Test que des coordonnées valides sans salle sont acceptées.
     */
    public function test_valid_coordinates_without_salle(): void
    {
        $this->assertTrue(
            $this->service->isLocationValid(6.3608, 2.4354) // Abomey-Calavi
        );
    }

    /**
     * Test que des coordonnées hors limites sont rejetées (latitude invalide).
     */
    public function test_invalid_latitude(): void
    {
        $this->assertFalse(
            $this->service->isLocationValid(100.0, 2.0)
        );
    }

    /**
     * Test que des coordonnées hors limites sont rejetées (longitude invalide).
     */
    public function test_invalid_longitude(): void
    {
        $this->assertFalse(
            $this->service->isLocationValid(6.0, 200.0)
        );
    }

    /**
     * Test getLocationInfo retourne la structure attendue sans salle.
     * Sans salle_id, is_valid est false (aucune salle à vérifier).
     */
    public function test_get_location_info_without_salle(): void
    {
        $info = $this->service->getLocationInfo(6.3608, 2.4354);

        $this->assertIsArray($info);
        $this->assertArrayHasKey('is_valid', $info);
        $this->assertArrayHasKey('nearest_location', $info);
        $this->assertFalse($info['is_valid']); // Pas de salle → pas de vérification
        $this->assertNull($info['nearest_location']);
    }
}
