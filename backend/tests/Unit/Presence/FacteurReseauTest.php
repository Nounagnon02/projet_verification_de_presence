<?php

namespace Tests\Unit\Presence;

use App\Models\Salle;
use App\Services\Presence\FacteurReseau;
use Tests\TestCase;

/**
 * Facteur réseau, sans base de données.
 *
 * Satisfait si le SSID ou le BSSID correspond, OU si l'IP client est dans la
 * plage « ip_range » de la salle — deux indices, pas un ET.
 */
class FacteurReseauTest extends TestCase
{
    private function salle(array $attributs = []): Salle
    {
        return new Salle($attributs + [
            'nom' => 'Salle Test', 'code' => 'ST01',
            'ssid_attendu' => 'ASIN-STAFF', 'bssid_attendu' => '20:58:69:69:ac:7c',
            'ip_range' => '10.53.8.0/24', 'hors_reseau' => false,
        ]);
    }

    public function test_non_configuree_ne_bloque_rien(): void
    {
        $salle = new Salle(['nom' => 'Libre', 'code' => 'LB01']);

        $this->assertNull(FacteurReseau::verifier($salle, null, null, '203.0.113.1'));
    }

    public function test_hors_reseau_ne_bloque_rien_meme_configuree(): void
    {
        $salle = $this->salle(['hors_reseau' => true]);

        $this->assertNull(FacteurReseau::verifier($salle, 'RESEAU-INCONNU', null, '203.0.113.1'));
    }

    public function test_bon_ssid_est_satisfait(): void
    {
        $verdict = FacteurReseau::verifier($this->salle(), 'ASIN-STAFF', null, '203.0.113.1');

        $this->assertTrue($verdict->satisfait);
    }

    public function test_ip_dans_la_plage_est_satisfaite_meme_avec_un_mauvais_ssid(): void
    {
        // Le facteur réseau est satisfait par L'UN OU L'AUTRE indice.
        $verdict = FacteurReseau::verifier($this->salle(), 'CAFE-VOISIN', null, '10.53.8.42');

        $this->assertTrue($verdict->satisfait);
    }

    public function test_mauvais_ssid_et_ip_hors_plage_est_refuse_sans_divulguer_le_ssid_attendu(): void
    {
        $verdict = FacteurReseau::verifier($this->salle(), 'CAFE-VOISIN', null, '203.0.113.1');

        $this->assertFalse($verdict->satisfait);
        $this->assertStringNotContainsString('ASIN-STAFF', $verdict->messageEtudiant);
        $this->assertSame('ASIN-STAFF', $verdict->detail['ssid_attendu']);
    }

    public function test_rien_transmis_oriente_vers_l_application_mobile(): void
    {
        $verdict = FacteurReseau::verifier($this->salle(), null, null, '203.0.113.1');

        $this->assertFalse($verdict->satisfait);
        $this->assertStringContainsString('application mobile', $verdict->messageEtudiant);
    }

    public function test_bssid_insensible_a_la_casse(): void
    {
        $verdict = FacteurReseau::verifier($this->salle(), null, '20:58:69:69:AC:7C', '203.0.113.1');

        $this->assertTrue($verdict->satisfait);
    }
}
