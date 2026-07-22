<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\Ue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests pour l'historique des présences (PresenceHistoryController).
 *
 * Couvre :
 * - Authentification (401)
 * - Liste paginée
 * - Filtres (statut, filière, niveau, date)
 * - Export CSV
 * - Export XLSX
 * - Export PDF
 */
class PresenceHistoryTest extends TestCase
{

    private User $admin;
    private string $bearerToken;
    private Filiere $filiere;
    private AnneeAcademique $annee;
    private Ec $ec;
    private Evenement $evenement;
    private Etudiant $etudiant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->bearerToken = $this->admin->createToken('test-token')->plainTextToken;

        $this->filiere = Filiere::create([
            'code'     => 'MIAGE',
            'intitule' => 'MIAGE',
            'niveau'   => 'M1',
        ]);

        $this->annee = AnneeAcademique::create([
            'libelle'    => '2025-2026',
            'date_debut' => '2025-10-01',
            'date_fin'   => '2026-09-30',
            'active'     => true,
        ]);

        $ue = Ue::create([
            'code'           => 'UE-HIST',
            'intitule'       => 'UE Historique',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 20,
        ]);

        $ec = Ec::create([
            'ue_id'          => $ue->id,
            'code'           => 'EC-HIST',
            'intitule'       => 'EC Historique',
            'volume_horaire' => 20,
        ]);
        $this->ec = $ec;

        $this->evenement = Evenement::create([
            'ec_id'       => $ec->id,
            'filiere_id'  => $this->filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => today()->format('Y-m-d'),
            'heure_debut' => '08:00',
            'heure_fin'   => '10:00',
            'salle'       => 'Salle Test',
        ]);

        $this->etudiant = Etudiant::create([
            'id'                => (string) Str::uuid(),
            'nom'               => 'DUPONT',
            'prenom'            => 'Jean',
            'matricule'         => 'HIST-001',
            'filiere_id'        => $this->filiere->id,
            'annee_id'          => $this->annee->id,
            'email'             => 'jean.hist@test.com',
            'identifiant_unique' => 'DUPONT_JEAN_HIST-001_MIAGE_M1',
        ]);
    }

    private function createPresence(string $statut = 'valide', ?Carbon $date = null): Presence
    {
        $event = Evenement::create([
            'ec_id'       => $this->ec->id,
            'filiere_id'  => $this->filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => today()->format('Y-m-d'),
            'heure_debut' => '08:00',
            'heure_fin'   => '10:00',
            'salle'       => 'Salle Test',
        ]);

        return Presence::create([
            'etudiant_id'  => $this->etudiant->id,
            'evenement_id' => $event->id,
            'statut'       => $statut,
            'heure_scan'   => $date ?? Carbon::now(),
            'ip_address'   => '192.168.1.1',
        ]);
    }

    // ── AUTH ─────────────────────────────────────────────────────

    public function test_non_authentifie_recoit_401(): void
    {
        $this->getJson('/api/admin/presence/history')->assertStatus(401);
        $this->getJson('/api/admin/presence/export?format=csv')->assertStatus(401);
    }

    // ── LISTE ─────────────────────────────────────────────────────

    public function test_admin_peut_lister_historique(): void
    {
        $this->createPresence('valide');
        $this->createPresence('absent');
        $this->createPresence('suspect');

        $response = $this->withToken($this->bearerToken)
            ->getJson('/api/admin/presence/history');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_historique_filtre_par_statut(): void
    {
        $this->createPresence('valide');
        $this->createPresence('absent');

        $response = $this->withToken($this->bearerToken)
            ->getJson('/api/admin/presence/history?statut=absent');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.statut', 'absent');
    }

    public function test_historique_filtre_par_date(): void
    {
        $this->createPresence('valide', Carbon::parse('2026-01-15 10:00:00'));
        $this->createPresence('absent', Carbon::parse('2026-02-20 10:00:00'));

        $response = $this->withToken($this->bearerToken)
            ->getJson('/api/admin/presence/history?date_debut=2026-02-01&date_fin=2026-02-28');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.statut', 'absent');
    }

    public function test_historique_filtre_par_filiere(): void
    {
        $this->createPresence('valide');

        // Créer une autre filière et un étudiant dans cette autre filière
        $autreFiliere = Filiere::create([
            'code'     => 'AUTRE',
            'intitule' => 'Autre Filière',
            'niveau'   => 'L3',
        ]);

        $autreEtudiant = Etudiant::create([
            'id'                => (string) Str::uuid(),
            'nom'               => 'MARTIN',
            'prenom'            => 'Sophie',
            'matricule'         => 'HIST-002',
            'filiere_id'        => $autreFiliere->id,
            'annee_id'          => $this->annee->id,
            'email'             => 'sophie.hist@test.com',
            'identifiant_unique' => 'MARTIN_SOPHIE_HIST-002_AUTRE_L3',
        ]);

        Presence::create([
            'etudiant_id'  => $autreEtudiant->id,
            'evenement_id' => $this->evenement->id,
            'statut'       => 'valide',
            'heure_scan'   => Carbon::now(),
        ]);

        $response = $this->withToken($this->bearerToken)
            ->getJson('/api/admin/presence/history?filiere_id=' . $this->filiere->id);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // ── EXPORT ────────────────────────────────────────────────────

    public function test_export_csv(): void
    {
        $this->createPresence('valide');
        $this->createPresence('absent');

        $response = $this->withToken($this->bearerToken)
            ->get('/api/admin/presence/export?format=csv');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Étudiant', $content);
        $this->assertStringContainsString('Présent', $content);
        $this->assertStringContainsString('Absent', $content);
    }

    public function test_export_xlsx(): void
    {
        $this->createPresence('valide');

        $response = $this->withToken($this->bearerToken)
            ->get('/api/admin/presence/export?format=xlsx');

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            $response->headers->get('Content-Type') ?? ''
        );
    }

    public function test_export_pdf(): void
    {
        $this->createPresence('valide');

        $response = $this->withToken($this->bearerToken)
            ->get('/api/admin/presence/export?format=pdf');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_export_par_defaut_en_csv(): void
    {
        $this->createPresence('valide');

        $response = $this->withToken($this->bearerToken)
            ->get('/api/admin/presence/export');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
