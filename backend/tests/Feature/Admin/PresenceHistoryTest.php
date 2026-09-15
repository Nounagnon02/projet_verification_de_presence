<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\AuditLog;
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

    public function test_la_recherche_ignore_la_casse_et_accepte_prenom_nom(): void
    {
        $this->createPresence('valide');

        // Avec like, PostgreSQL distinguait les majuscules : « dupont » ne trouvait rien.
        foreach (['dupont', 'Dupont', 'Jean DUPONT', 'hist-001'] as $terme) {
            $this->withToken($this->bearerToken)
                ->getJson('/api/admin/presence/history?search=' . urlencode($terme))
                ->assertOk()
                ->assertJsonCount(1, 'data');
        }
    }

    public function test_le_tri_porte_sur_toute_la_selection(): void
    {
        $this->createPresence('valide');
        $martin = Etudiant::create([
            'id' => (string) Str::uuid(), 'nom' => 'MARTIN', 'prenom' => 'Sophie', 'matricule' => 'HIST-003',
            'filiere_id' => $this->filiere->id, 'annee_id' => $this->annee->id,
            'email' => 'sophie.tri@test.com', 'identifiant_unique' => 'MARTIN_SOPHIE_HIST-003_MIAGE_M1',
        ]);
        Presence::create(['etudiant_id' => $martin->id, 'evenement_id' => $this->evenement->id, 'statut' => 'valide', 'heure_scan' => Carbon::now()]);

        // Une ligne par page : le premier nom dit si le tri porte sur toute la sélection.
        $premier = fn (string $sens) => $this->withToken($this->bearerToken)
            ->getJson("/api/admin/presence/history?tri=etudiant&sens={$sens}&per_page=1&filiere_id={$this->filiere->id}")
            ->assertOk()
            ->json('data.0.etudiant.nom');

        $this->assertSame('DUPONT', $premier('asc'));
        $this->assertSame('MARTIN', $premier('desc'));
    }

    public function test_chaque_presence_dit_son_origine_et_qui_en_a_decide(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'name' => 'Admin Histoire', 'email' => 'hist-sa-' . Str::random(5) . '@test.com']);
        $jeton = $admin->createToken('t')->plainTextToken;

        $scan = $this->createPresence('valide');
        $examinee = $this->createPresence('suspect');
        $this->withToken($jeton)
            ->patchJson("/api/admin/presence/{$examinee->id}/validate", ['action' => 'rejeter', 'motif' => 'Téléphone prêté'])
            ->assertOk();
        // Trace laissée par le service de saisie manuelle.
        $saisie = $this->createPresence('valide');
        $saisie->update(['validated_by' => $admin->id, 'validation_motif' => 'Téléphone déchargé']);
        AuditLog::create([
            'action' => 'presence.saisie_manuelle', 'model_type' => Presence::class, 'model_id' => $saisie->id,
            'user_id' => $admin->id, 'new_values' => ['statut' => 'valide'],
        ]);

        $lignes = collect($this->withToken($jeton)->getJson('/api/admin/presence/history?per_page=100')->assertOk()->json('data'))->keyBy('id');

        $this->assertSame('scan', $lignes[$scan->id]['origine']['origine']);
        $this->assertNull($lignes[$scan->id]['origine']['decide_par']);
        $this->assertSame('rejetee_apres_examen', $lignes[$examinee->id]['origine']['origine']);
        $this->assertSame('Admin Histoire', $lignes[$examinee->id]['origine']['decide_par']);
        $this->assertSame('Téléphone prêté', $lignes[$examinee->id]['origine']['motif']);
        $this->assertSame('saisie_manuelle', $lignes[$saisie->id]['origine']['origine']);
        $this->assertSame('Téléphone déchargé', $lignes[$saisie->id]['origine']['motif']);
    }

    // ── EXPORT ────────────────────────────────────────────────────

    public function test_les_exports_nomment_chaque_statut_et_l_origine(): void
    {
        $this->createPresence('rejete');

        $contenu = $this->withToken($this->bearerToken)
            ->get('/api/admin/presence/export?format=csv')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Origine', $contenu);
        $this->assertStringContainsString('Rejeté', $contenu);
        $this->assertStringNotContainsString(',rejete,', $contenu);

        $this->withToken($this->bearerToken)->get('/api/admin/presence/export?format=pdf')->assertOk();
        $this->withToken($this->bearerToken)->get('/api/admin/presence/export?format=xlsx')->assertOk();
    }


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
