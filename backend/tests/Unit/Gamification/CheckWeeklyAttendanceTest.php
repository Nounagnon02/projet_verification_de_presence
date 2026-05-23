<?php

namespace Tests\Unit\Gamification;

use App\Actions\Gamification\CheckWeeklyAttendance;
use App\Models\AnneeAcademique;
use App\Models\AuditLog;
use App\Models\Ec;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\Ue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests unitaires pour la récompense de semaine parfaite (CDC 12.1).
 *
 * Vérifie :
 * - 100% de présence → points attribués
 * - Moins de 100% → pas de points
 * - Pas d'événement dans la semaine → pas de points
 * - Pas de double-récompense (anti-doublon via AuditLog)
 */
class CheckWeeklyAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Filiere $filiere;
    private AnneeAcademique $annee;
    private Etudiant $etudiant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filiere = Filiere::create([
            'code'     => 'TEST-G',
            'intitule' => 'Filière Gamification Test',
            'niveau'   => 'L3',
        ]);

        $this->annee = AnneeAcademique::create([
            'libelle'    => '2025-2026',
            'date_debut' => '2025-10-01',
            'date_fin'   => '2026-09-30',
            'active'     => true,
        ]);

        $ue = Ue::create([
            'code'           => 'UE-GT',
            'intitule'       => 'UE Gamification Test',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 20,
        ]);

        $ec = Ec::create([
            'ue_id'          => $ue->id,
            'code'           => 'EC-GT',
            'intitule'       => 'EC Gamification Test',
            'volume_horaire' => 20,
        ]);

        $this->etudiant = Etudiant::create([
            'id'                 => (string) Str::uuid(),
            'nom'                => 'GAMIF',
            'prenom'             => 'TEST',
            'matricule'          => 'GAM-001',
            'filiere_id'         => $this->filiere->id,
            'annee_id'           => $this->annee->id,
            'email'              => 'gamif@test.com',
            'identifiant_unique' => 'GAMIF_TEST_GAM-001_TEST-G_L3',
            'points'             => 0,
        ]);
    }

    /**
     * Crée un événement à une date donnée de la semaine courante.
     */
    private function creeEvenement(string $dayName): Evenement
    {
        $now = Carbon::now();
        $date = $now->copy()->startOfWeek(Carbon::MONDAY)->modify($dayName);

        return Evenement::create([
            'ec_id'       => Ec::first()->id,
            'filiere_id'  => $this->filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => $date->format('Y-m-d'),
            'heure_debut' => '08:00:00',
            'heure_fin'   => '10:00:00',
            'salle'       => 'Salle G',
            'statut'      => 'termine',
        ]);
    }

    /**
     * Crée une présence valide pour un étudiant et un événement.
     */
    private function creePresence(Evenement $evenement): Presence
    {
        return Presence::create([
            'etudiant_id'       => $this->etudiant->id,
            'evenement_id'      => $evenement->id,
            'heure_scan'        => Carbon::parse($evenement->date->toDateString() . ' ' . $evenement->heure_debut),
            'device_fingerprint'=> 'device-test-gam-' . Str::random(6),
            'ip_address'        => '127.0.0.1',
            'statut'            => 'valide',
        ]);
    }

    // ── TESTS ─────────────────────────────────────────────────────

    public function test_semaine_parfaite_attribue_20_points(): void
    {
        // Créer 3 événements cette semaine
        $e1 = $this->creeEvenement('Monday');
        $e2 = $this->creeEvenement('Wednesday');
        $e3 = $this->creeEvenement('Friday');

        // Présences pour tous les événements
        $this->creePresence($e1);
        $this->creePresence($e2);
        $this->creePresence($e3);

        $action = new CheckWeeklyAttendance();
        $result = $action->execute($this->etudiant);

        $this->assertTrue($result['perfect']);
        $this->assertEquals(20, $result['points_awarded']);
        $this->assertEquals(20, $this->etudiant->fresh()->points);

        // AuditLog doit contenir la trace
        $this->assertDatabaseHas('audit_logs', [
            'model_type' => Etudiant::class,
            'model_id'   => $this->etudiant->id,
            'action'     => 'weekly_bonus',
        ]);
    }

    public function test_presence_partielle_ne_donne_pas_de_points(): void
    {
        $e1 = $this->creeEvenement('Monday');
        $e2 = $this->creeEvenement('Wednesday');

        // Un seul événement scanné → semaine incomplète
        $this->creePresence($e1);

        $action = new CheckWeeklyAttendance();
        $result = $action->execute($this->etudiant);

        $this->assertFalse($result['perfect']);
        $this->assertEquals(0, $result['points_awarded']);
        $this->assertEquals(0, $this->etudiant->fresh()->points);
    }

    public function test_aucun_evenement_dans_la_semaine(): void
    {
        // Aucun événement créé

        $action = new CheckWeeklyAttendance();
        $result = $action->execute($this->etudiant);

        $this->assertFalse($result['perfect']);
        $this->assertEquals(0, $result['points_awarded']);
        $this->assertEquals('0/0', $result['progress']);
    }

    public function test_pas_de_double_recompense(): void
    {
        $e1 = $this->creeEvenement('Monday');
        $e2 = $this->creeEvenement('Tuesday');

        $this->creePresence($e1);
        $this->creePresence($e2);

        $action = new CheckWeeklyAttendance();

        // Premier appel : récompense accordée
        $first = $action->execute($this->etudiant);
        $this->assertTrue($first['perfect']);
        $this->assertEquals(20, $first['points_awarded']);
        $this->assertEquals(20, $this->etudiant->fresh()->points);

        // Deuxième appel : déjà récompensé, 0 points supplémentaires
        $second = $action->execute($this->etudiant);
        $this->assertTrue($second['perfect']);
        $this->assertEquals(0, $second['points_awarded']);
        $this->assertEquals(20, $this->etudiant->fresh()->points); // Toujours 20
    }

    public function test_presence_suspecte_ne_compte_pas_pour_la_semaine_parfaite(): void
    {
        $e1 = $this->creeEvenement('Monday');
        $e2 = $this->creeEvenement('Wednesday');

        // Deux présences, mais l'une est suspecte
        $this->creePresence($e1);
        Presence::create([
            'etudiant_id'       => $this->etudiant->id,
            'evenement_id'      => $e2->id,
            'heure_scan'        => Carbon::parse($e2->date->toDateString() . ' ' . $e2->heure_debut),
            'device_fingerprint'=> 'device-suspect',
            'ip_address'        => '127.0.0.1',
            'statut'            => 'suspect',  // ← suspect, pas valide
        ]);

        $action = new CheckWeeklyAttendance();
        $result = $action->execute($this->etudiant);

        $this->assertFalse($result['perfect']);
        $this->assertEquals(0, $result['points_awarded']);
    }
}
