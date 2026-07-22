<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Filiere;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tests pour les routes d'import (ImportController).
 *
 * Couvre :
 * - Import CSV des étudiants (US02)
 * - Import emploi du temps via Gemini (US03) avec service mocké
 */
class ImportApiTest extends TestCase
{

    private User $admin;
    private string $bearerToken;
    private Filiere $filiere;
    private AnneeAcademique $annee;

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
    }

    // ── IMPORT CSV ÉTUDIANTS ──────────────────────────────────────

    public function test_import_csv_avec_succes(): void
    {
        $csvContent = "nom,prenom,matricule,filiere_code,annee_libelle,email\n" .
            "Dupont,Jean,IMP-001,MIAGE,2025-2026,jean.dupont@test.com\n" .
            "Martin,Sophie,IMP-002,MIAGE,2025-2026,sophie.martin@test.com";

        $file = UploadedFile::fake()->create('students.csv', strlen($csvContent));
        file_put_contents($file->getPathname(), $csvContent);

        $response = $this->withToken($this->bearerToken)
            ->postJson('/api/admin/import/students', [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.success', 2);

        $this->assertDatabaseHas('etudiants', ['matricule' => 'IMP-001']);
        $this->assertDatabaseHas('etudiants', ['matricule' => 'IMP-002']);
    }

    public function test_import_csv_filtre_les_lignes_invalides(): void
    {
        $csvContent = "nom,prenom,matricule,filiere_code,annee_libelle,email\n" .
            "Dupont,Jean,IMP-003,MIAGE,2025-2026,jean.dupont@test.com\n" .
            "Invalide,SansFiliere,IMP-004,FAKE,2025-2026,bad@test.com\n"; // filiere_code n'existe pas

        $file = UploadedFile::fake()->create('students.csv', strlen($csvContent));
        file_put_contents($file->getPathname(), $csvContent);

        $response = $this->withToken($this->bearerToken)
            ->postJson('/api/admin/import/students', [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.success', 1)
            ->assertJsonPath('data.total', 2);

        $this->assertCount(1, $response->json('data.errors'));
    }

    public function test_import_csv_echoue_sans_token(): void
    {
        $file = UploadedFile::fake()->create('students.csv');

        $response = $this->postJson('/api/admin/import/students', [
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    // ── IMPORT EMPLOI DU TEMPS GEMINI (ASYNCHRONE) ─────────────────

    public function test_import_schedule_lance_analyse_async(): void
    {
        Queue::fake();

        $file = UploadedFile::fake()->create('edt.pdf', 1024, 'application/pdf');
        // Écrire des magic bytes PDF valides dans le fichier
        file_put_contents($file->getPathname(), '%PDF-1.4 ' . str_repeat('x', 1016));

        $response = $this->withToken($this->bearerToken)
            ->postJson('/api/admin/import/schedule', [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['analysis_id', 'status']]);

        // Vérifier qu'un enregistrement Analyse a été créé en base
        // (le statut final peut être 'pending', 'processing', 'completed' ou 'failed'
        //  selon la vitesse de la queue synchrone dans l'environnement de test)
        $this->assertDatabaseHas('analyses', [
            'type' => 'schedule',
        ]);

        // Récupérer l'ID et tester le endpoint de statut
        $analysisId = $response->json('data.analysis_id');
        $statusResponse = $this->withToken($this->bearerToken)
            ->getJson("/api/admin/import/analysis-status/{$analysisId}");

        $statusResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'schedule')
            ->assertJsonStructure([
                'data' => ['analysis_id', 'status', 'type'],
            ]);
    }

    public function test_import_courses_lance_analyse_async(): void
    {
        Queue::fake();

        $file = UploadedFile::fake()->create('cours.pdf', 1024, 'application/pdf');
        // Écrire des magic bytes PDF valides dans le fichier
        file_put_contents($file->getPathname(), '%PDF-1.4 ' . str_repeat('x', 1016));

        $response = $this->withToken($this->bearerToken)
            ->postJson('/api/admin/import/courses', [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['analysis_id', 'status']]);

        // Vérifier qu'un enregistrement Analyse existe en base
        $this->assertDatabaseHas('analyses', [
            'type' => 'courses',
        ]);

        // Tester le endpoint de statut
        $analysisId = $response->json('data.analysis_id');
        $statusResponse = $this->withToken($this->bearerToken)
            ->getJson("/api/admin/import/analysis-status/{$analysisId}");

        $statusResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'courses')
            ->assertJsonStructure([
                'data' => ['analysis_id', 'status', 'type'],
            ]);
    }

    public function test_import_schedule_echoue_sans_pdf(): void
    {
        $file = UploadedFile::fake()->create('document.txt', 100);

        $response = $this->withToken($this->bearerToken)
            ->postJson('/api/admin/import/schedule', [
                'file' => $file,
            ]);

        $response->assertStatus(422);
    }
}
