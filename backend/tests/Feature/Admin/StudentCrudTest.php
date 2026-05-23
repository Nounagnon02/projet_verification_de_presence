<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests CRUD pour la gestion des étudiants (StudentController).
 *
 * Couvre :
 * - Authentification requise (401 sans token)
 * - Liste paginée avec filtres
 * - Création avec identifiant déterministe (CDC 7.1.3)
 * - Consultation, modification, suppression
 */
class StudentCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Filiere $filiere;
    private AnneeAcademique $annee;
    private string $bearerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name'  => 'Admin Test',
            'email' => 'admin@test.com',
        ]);

        $this->bearerToken = $this->admin->createToken('test-token')->plainTextToken;

        $this->filiere = Filiere::create([
            'code'     => 'MIAGE',
            'intitule' => 'Méthodes Informatiques Appliquées à la Gestion',
            'niveau'   => 'M1',
        ]);

        $this->annee = AnneeAcademique::create([
            'libelle'    => '2025-2026',
            'date_debut' => '2025-10-01',
            'date_fin'   => '2026-09-30',
            'active'     => true,
        ]);
    }

    // ── AUTH ─────────────────────────────────────────────────────

    public function test_non_authentifie_recoit_401(): void
    {
        $response = $this->getJson('/api/admin/students');
        $response->assertStatus(401);

        $response = $this->postJson('/api/admin/students', []);
        $response->assertStatus(401);
    }

    // ── LISTE ─────────────────────────────────────────────────────

    public function test_admin_peut_lister_les_etudiants(): void
    {
        Etudiant::create([
            'id'                 => (string) Str::uuid(),
            'nom'                => 'DUPONT',
            'prenom'             => 'JEAN',
            'matricule'          => 'STU-001',
            'filiere_id'         => $this->filiere->id,
            'annee_id'           => $this->annee->id,
            'email'              => 'jean.dupont@test.com',
            'identifiant_unique' => 'DUPONT_JEAN_STU-001_MIAGE_M1',
        ]);

        Etudiant::create([
            'id'                 => (string) Str::uuid(),
            'nom'                => 'MARTIN',
            'prenom'             => 'SOPHIE',
            'matricule'          => 'STU-002',
            'filiere_id'         => $this->filiere->id,
            'annee_id'           => $this->annee->id,
            'email'              => 'sophie.martin@test.com',
            'identifiant_unique' => 'MARTIN_SOPHIE_STU-002_MIAGE_M1',
        ]);

        $response = $this->withToken($this->bearerToken)
            ->getJson('/api/admin/students');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'nom', 'matricule']]]);
    }

    public function test_liste_filtre_par_recherche(): void
    {
        Etudiant::create([
            'id'                 => (string) Str::uuid(),
            'nom'                => 'DUPONT',
            'prenom'             => 'JEAN',
            'matricule'          => 'STU-001',
            'filiere_id'         => $this->filiere->id,
            'annee_id'           => $this->annee->id,
            'email'              => 'jean.dupont@test.com',
            'identifiant_unique' => 'DUPONT_JEAN_STU-001_MIAGE_M1',
        ]);

        $response = $this->withToken($this->bearerToken)
            ->getJson('/api/admin/students?search=MARTIN');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // ── CRÉATION ──────────────────────────────────────────────────

    public function test_admin_peut_creer_un_etudiant(): void
    {
        $payload = [
            'nom'        => 'Dupont',
            'prenom'     => 'Jean',
            'matricule'  => 'STU-003',
            'filiere_id' => $this->filiere->id,
            'annee_id'   => $this->annee->id,
            'email'      => 'jean.dupont+new@test.com',
        ];

        $response = $this->withToken($this->bearerToken)
            ->postJson('/api/admin/students', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nom', 'DUPONT')          // mis en majuscules
            ->assertJsonPath('data.matricule', 'STU-003')
            ->assertJsonPath('data.identifiant_unique', 'DUPONT_JEAN_STU_003_MIAGE_2025_2026')
            ->assertJsonStructure(['data' => ['id', 'nom', 'prenom', 'matricule', 'identifiant_unique']]);

        $this->assertDatabaseHas('etudiants', ['matricule' => 'STU-003']);
    }

    public function test_creation_echoue_si_matricule_existe_deja(): void
    {
        Etudiant::create([
            'id'                 => (string) Str::uuid(),
            'nom'                => 'EXISTANT',
            'prenom'             => 'USER',
            'matricule'          => 'STU-EXIST',
            'filiere_id'         => $this->filiere->id,
            'annee_id'           => $this->annee->id,
            'email'              => 'exist@test.com',
            'identifiant_unique' => 'EXISTANT_USER_STU-EXIST_MIAGE_M1',
        ]);

        $response = $this->withToken($this->bearerToken)
            ->postJson('/api/admin/students', [
                'nom'        => 'Dupont',
                'prenom'     => 'Jean',
                'matricule'  => 'STU-EXIST',
                'filiere_id' => $this->filiere->id,
                'annee_id'   => $this->annee->id,
                'email'      => 'autre@test.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('matricule');
    }

    // ── CONSULTATION ──────────────────────────────────────────────

    public function test_admin_peut_consulter_un_etudiant(): void
    {
        $etudiant = Etudiant::create([
            'id'                 => (string) Str::uuid(),
            'nom'                => 'DUPONT',
            'prenom'             => 'JEAN',
            'matricule'          => 'STU-004',
            'filiere_id'         => $this->filiere->id,
            'annee_id'           => $this->annee->id,
            'email'              => 'show@test.com',
            'identifiant_unique' => 'DUPONT_JEAN_STU-004_MIAGE_M1',
        ]);

        $response = $this->withToken($this->bearerToken)
            ->getJson("/api/admin/students/{$etudiant->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.nom', 'DUPONT')
            ->assertJsonPath('data.matricule', 'STU-004');
    }

    // ── MISE À JOUR ───────────────────────────────────────────────

    public function test_admin_peut_modifier_un_etudiant(): void
    {
        $etudiant = Etudiant::create([
            'id'                 => (string) Str::uuid(),
            'nom'                => 'DUPONT',
            'prenom'             => 'JEAN',
            'matricule'          => 'STU-005',
            'filiere_id'         => $this->filiere->id,
            'annee_id'           => $this->annee->id,
            'email'              => 'update@test.com',
            'identifiant_unique' => 'DUPONT_JEAN_STU-005_MIAGE_M1',
        ]);

        $response = $this->withToken($this->bearerToken)
            ->putJson("/api/admin/students/{$etudiant->id}", [
                'prenom' => 'Pierre',
                'email'  => 'pierre.dupont@test.com',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.prenom', 'PIERRE');

        $this->assertDatabaseHas('etudiants', [
            'id'     => $etudiant->id,
            'prenom' => 'PIERRE',
            'email'  => 'pierre.dupont@test.com',
        ]);
    }

    // ── SUPPRESSION ───────────────────────────────────────────────

    public function test_admin_peut_supprimer_un_etudiant(): void
    {
        $etudiant = Etudiant::create([
            'id'                 => (string) Str::uuid(),
            'nom'                => 'DUPONT',
            'prenom'             => 'JEAN',
            'matricule'          => 'STU-006',
            'filiere_id'         => $this->filiere->id,
            'annee_id'           => $this->annee->id,
            'email'              => 'delete@test.com',
            'identifiant_unique' => 'DUPONT_JEAN_STU-006_MIAGE_M1',
        ]);

        $response = $this->withToken($this->bearerToken)
            ->deleteJson("/api/admin/students/{$etudiant->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('etudiants', ['id' => $etudiant->id]);
    }
}
