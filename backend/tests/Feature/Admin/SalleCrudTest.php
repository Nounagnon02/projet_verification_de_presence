<?php

namespace Tests\Feature\Admin;

use App\Models\Etablissement;
use App\Models\Salle;
use App\Models\User;
use Tests\TestCase;

/**
 * Tests CRUD pour la gestion des salles (SalleController).
 *
 * Couvre :
 * - Authentification requise (401 sans token)
 * - Liste paginée avec recherche
 * - Création d'une salle
 * - Consultation
 * - Modification
 * - Suppression
 */
class SalleCrudTest extends TestCase
{

    private User $admin;
    private string $bearerToken;
    private Etablissement $etablissement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name'  => 'Admin Test',
            'email' => 'admin@salles.test',
        ]);

        $this->bearerToken = $this->admin->createToken('test-token')->plainTextToken;

        $this->etablissement = Etablissement::create([
            'code'  => 'ENT-001',
            'nom'   => 'Entité Test',
            'email' => 'entite@test.com',
        ]);
    }

    public function test_non_authentifie_recoit_401(): void
    {
        $response = $this->getJson('/api/admin/salles');
        $response->assertStatus(401);

        $response = $this->postJson('/api/admin/salles', []);
        $response->assertStatus(401);
    }

    public function test_admin_peut_lister_les_salles(): void
    {
        Salle::create([
            'nom'               => 'Salle A',
            'code'              => 'SA-001',
            'etablissement_id'  => $this->etablissement->id,
            'latitude'          => 6.3608,
            'longitude'         => 2.4354,
            'rayon_geofence_m'  => 50,
            'actif'             => true,
        ]);

        Salle::create([
            'nom'               => 'Salle B',
            'code'              => 'SB-002',
            'etablissement_id'  => $this->etablissement->id,
            'actif'             => true,
        ]);

        $response = $this->withToken($this->bearerToken)
            ->getJson('/api/admin/salles');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_liste_salles_filtre_par_recherche(): void
    {
        Salle::create([
            'nom'               => 'Amphi 1000',
            'code'              => 'AMP-001',
            'etablissement_id'  => $this->etablissement->id,
            'actif'             => true,
        ]);

        Salle::create([
            'nom'               => 'Salle TP',
            'code'              => 'TP-001',
            'etablissement_id'  => $this->etablissement->id,
            'actif'             => true,
        ]);

        $response = $this->withToken($this->bearerToken)
            ->getJson('/api/admin/salles?search=Amphi');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nom', 'Amphi 1000');
    }

    public function test_admin_peut_creer_une_salle(): void
    {
        $payload = [
            'nom'               => 'Nouvelle Salle',
            'code'              => 'NS-001',
            'etablissement_id'  => $this->etablissement->id,
            'latitude'          => 6.3650,
            'longitude'         => 2.4180,
            'rayon_geofence_m'  => 30,
            'hors_reseau'       => false,
            'actif'             => true,
        ];

        $response = $this->withToken($this->bearerToken)
            ->postJson('/api/admin/salles', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nom', 'Nouvelle Salle')
            ->assertJsonPath('data.code', 'NS-001');

        $this->assertDatabaseHas('salles', ['code' => 'NS-001']);
    }

    public function test_admin_peut_consulter_une_salle(): void
    {
        $salle = Salle::create([
            'nom'               => 'Salle Consultation',
            'code'              => 'SC-001',
            'etablissement_id'  => $this->etablissement->id,
            'actif'             => true,
        ]);

        $response = $this->withToken($this->bearerToken)
            ->getJson("/api/admin/salles/{$salle->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.nom', 'Salle Consultation')
            ->assertJsonPath('data.code', 'SC-001');
    }

    public function test_admin_peut_modifier_une_salle(): void
    {
        $salle = Salle::create([
            'nom'               => 'Ancien Nom',
            'code'              => 'AN-001',
            'etablissement_id'  => $this->etablissement->id,
            'actif'             => true,
        ]);

        $response = $this->withToken($this->bearerToken)
            ->putJson("/api/admin/salles/{$salle->id}", [
                'nom'  => 'Nouveau Nom',
                'code' => 'AN-001',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.nom', 'Nouveau Nom');

        $this->assertDatabaseHas('salles', [
            'id'  => $salle->id,
            'nom' => 'Nouveau Nom',
        ]);
    }

    public function test_admin_peut_supprimer_une_salle(): void
    {
        $salle = Salle::create([
            'nom'               => 'Salle à supprimer',
            'code'              => 'SUP-001',
            'etablissement_id'  => $this->etablissement->id,
            'actif'             => true,
        ]);

        $response = $this->withToken($this->bearerToken)
            ->deleteJson("/api/admin/salles/{$salle->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('salles', ['id' => $salle->id]);
    }

    public function test_creation_salle_sans_coordonnees(): void
    {
        $payload = [
            'nom'               => 'Salle sans GPS',
            'code'              => 'SGPS-001',
            'etablissement_id'  => $this->etablissement->id,
            'hors_reseau'       => true,
            'actif'             => true,
        ];

        $response = $this->withToken($this->bearerToken)
            ->postJson('/api/admin/salles', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('salles', ['code' => 'SGPS-001']);
    }

    public function test_creation_salle_inactive(): void
    {
        $payload = [
            'nom'               => 'Salle inactive',
            'code'              => 'INA-001',
            'etablissement_id'  => $this->etablissement->id,
            'actif'             => false,
        ];

        $response = $this->withToken($this->bearerToken)
            ->postJson('/api/admin/salles', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.actif', false);
    }
}
