<?php

namespace Tests\Unit\Presence;

use App\Models\Salle;
use App\Services\Presence\GeoReperage;
use Tests\TestCase;

/**
 * Facteur de géolocalisation, sans base de données : Salle::distanceMetres()
 * et isWithinGeofence() ne lisent que les attributs du modèle, pas persisté
 * ici.
 */
class GeoReperageTest extends TestCase
{
    private function salle(): Salle
    {
        return new Salle([
            'nom' => 'Salle Test', 'code' => 'ST01',
            'latitude' => 6.3608, 'longitude' => 2.4354, 'rayon_geofence_m' => 50,
        ]);
    }

    public function test_non_configuree_ne_bloque_rien(): void
    {
        $salle = new Salle(['nom' => 'Sans GPS', 'code' => 'SG01']);

        $this->assertNull(GeoReperage::verifier($salle, 6.3608, 2.4354));
    }

    public function test_dans_le_rayon_est_satisfait(): void
    {
        $verdict = GeoReperage::verifier($this->salle(), 6.3608, 2.4354);

        $this->assertTrue($verdict->satisfait);
    }

    public function test_hors_rayon_est_refuse_sans_divulguer_la_distance(): void
    {
        // ~1,5 km de la salle.
        $verdict = GeoReperage::verifier($this->salle(), 6.3720, 2.4220);

        $this->assertFalse($verdict->satisfait);
        $this->assertSame('Vous ne semblez pas être dans la salle du cours.', $verdict->messageEtudiant);
        // Le détail, lui, existe — mais à l'usage exclusif de l'anomalie.
        $this->assertArrayHasKey('distance_metres', $verdict->detail);
        $this->assertArrayHasKey('rayon_m', $verdict->detail);
    }

    public function test_position_absente_a_un_message_distinct_de_hors_zone(): void
    {
        // Régression : distanceMetres(null, ...) devenait « 0 m », un refus
        // qui se contredisait lui-même sans dire que le GPS manquait.
        $verdict = GeoReperage::verifier($this->salle(), null, null);

        $this->assertFalse($verdict->satisfait);
        $this->assertStringContainsString('autorisez la géolocalisation', $verdict->messageEtudiant);
        $this->assertNull($verdict->detail['distance_metres']);
    }
}
