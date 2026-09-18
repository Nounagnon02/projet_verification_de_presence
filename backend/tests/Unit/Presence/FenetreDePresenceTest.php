<?php

namespace Tests\Unit\Presence;

use App\Models\Evenement;
use App\Services\Presence\FenetreDePresence;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Fenêtre horaire de prise de présence — sans base de données ni requête
 * HTTP : Evenement::ouvertureScan()/fermetureScan() ne lisent que leurs
 * propres attributs et la configuration.
 */
class FenetreDePresenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('presence.scan.minutes_avant_fin', 15);
        Config::set('presence.scan.minutes_apres_fin', 10);
    }

    private function evenement(string $heureDebut, string $heureFin): Evenement
    {
        return new Evenement([
            'date'        => '2026-01-15',
            'heure_debut' => $heureDebut,
            'heure_fin'   => $heureFin,
        ]);
    }

    public function test_refuse_avant_l_ouverture(): void
    {
        $verdict = FenetreDePresence::verifier(
            $this->evenement('08:00', '10:00'),
            Carbon::parse('2026-01-15 09:44:00'),
        );

        $this->assertFalse($verdict->satisfait);
        $this->assertSame(403, $verdict->statutHttp);
        $this->assertStringContainsString('9:45', $verdict->messageEtudiant);
    }

    public function test_accepte_a_l_ouverture_exacte(): void
    {
        $verdict = FenetreDePresence::verifier(
            $this->evenement('08:00', '10:00'),
            Carbon::parse('2026-01-15 09:45:00'),
        );

        $this->assertTrue($verdict->satisfait);
    }

    public function test_accepte_a_la_fermeture_exacte(): void
    {
        $verdict = FenetreDePresence::verifier(
            $this->evenement('08:00', '10:00'),
            Carbon::parse('2026-01-15 10:10:00'),
        );

        $this->assertTrue($verdict->satisfait);
    }

    public function test_refuse_apres_la_fermeture(): void
    {
        $verdict = FenetreDePresence::verifier(
            $this->evenement('08:00', '10:00'),
            Carbon::parse('2026-01-15 10:11:00'),
        );

        $this->assertFalse($verdict->satisfait);
        $this->assertStringContainsString('10:10', $verdict->messageEtudiant);
    }

    public function test_refuse_en_milieu_de_seance(): void
    {
        // Régression : la fenêtre s'ouvrait à l'heure de début, ce qui
        // permettait de valider sa présence puis de quitter la salle.
        $verdict = FenetreDePresence::verifier(
            $this->evenement('08:00', '10:00'),
            Carbon::parse('2026-01-15 08:30:00'),
        );

        $this->assertFalse($verdict->satisfait);
    }
}
