<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Ue;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Tests CRUD pour la gestion des événements de cours (EvenementController).
 *
 * Couvre :
 * - Authentification (401)
 * - Création, liste, consultation, modification, suppression
 * - Rejet d'un EC terminé
 */
class EvenementCrudTest extends TestCase
{

    private User $admin;
    private string $bearerToken;
    private Filiere $filiere;
    private AnneeAcademique $annee;
    private Ec $ec;
    private Ec $ecTermine;

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
            'code'           => 'UE-EVT',
            'intitule'       => 'UE Événements',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 40,
        ]);

        $this->ec = Ec::create([
            'ue_id'          => $ue->id,
            'code'           => 'EC-EVT-01',
            'intitule'       => 'EC Normal',
            'volume_horaire' => 20,
        ]);

        $ue2 = Ue::create([
            'code'           => 'UE-EVT2',
            'intitule'       => 'UE Terminée',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 10,
        ]);

        $this->ecTermine = Ec::create([
            'ue_id'          => $ue2->id,
            'code'           => 'EC-EVT-TERM',
            'intitule'       => 'EC Terminé',
            'volume_horaire' => 10,
            'statut'         => 'termine',
        ]);
    }

    public function test_non_authentifie_recoit_401(): void
    {
        $this->getJson('/api/admin/evenements')->assertStatus(401);
        $this->postJson('/api/admin/evenements', [])->assertStatus(401);
    }

    public function test_admin_peut_creer_un_evenement(): void
    {
        $response = $this->withToken($this->bearerToken)
            ->postJson('/api/admin/evenements', [
                'ec_id'       => $this->ec->id,
                'filiere_id'  => $this->filiere->id,
                'annee_id'    => $this->annee->id,
                'date'        => today()->format('Y-m-d'),
                'heure_debut' => '08:00',
                'heure_fin'   => '10:00',
                'salle'       => 'Salle 101',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('evenements', ['ec_id' => $this->ec->id]);
    }

    public function test_admin_peut_lister_les_evenements(): void
    {
        Evenement::create([
            'ec_id'       => $this->ec->id,
            'filiere_id'  => $this->filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => today()->format('Y-m-d'),
            'heure_debut' => '08:00',
            'heure_fin'   => '10:00',
            'salle'       => 'Salle A',
        ]);

        Evenement::create([
            'ec_id'       => $this->ec->id,
            'filiere_id'  => $this->filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => today()->format('Y-m-d'),
            'heure_debut' => '10:00',
            'heure_fin'   => '12:00',
            'salle'       => 'Salle B',
        ]);

        $response = $this->withToken($this->bearerToken)
            ->getJson('/api/admin/evenements');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_peut_consulter_un_evenement(): void
    {
        $evenement = Evenement::create([
            'ec_id'       => $this->ec->id,
            'filiere_id'  => $this->filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => today()->format('Y-m-d'),
            'heure_debut' => '08:00',
            'heure_fin'   => '10:00',
            'salle'       => 'Salle 101',
        ]);

        $response = $this->withToken($this->bearerToken)
            ->getJson("/api/admin/evenements/{$evenement->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.salle', 'Salle 101');
    }

    public function test_admin_peut_modifier_un_evenement(): void
    {
        $evenement = Evenement::create([
            'ec_id'       => $this->ec->id,
            'filiere_id'  => $this->filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => today()->format('Y-m-d'),
            'heure_debut' => '08:00',
            'heure_fin'   => '10:00',
            'salle'       => 'Salle Originale',
        ]);

        $response = $this->withToken($this->bearerToken)
            ->putJson("/api/admin/evenements/{$evenement->id}", [
                'salle' => 'Salle Modifiée',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.salle', 'Salle Modifiée');
    }

    public function test_admin_peut_supprimer_un_evenement(): void
    {
        $evenement = Evenement::create([
            'ec_id'       => $this->ec->id,
            'filiere_id'  => $this->filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => today()->format('Y-m-d'),
            'heure_debut' => '08:00',
            'heure_fin'   => '10:00',
            'salle'       => 'Salle 101',
        ]);

        $response = $this->withToken($this->bearerToken)
            ->deleteJson("/api/admin/evenements/{$evenement->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('evenements', ['id' => $evenement->id]);
    }

    public function test_creation_evenement_rejetee_si_ec_termine(): void
    {
        $response = $this->withToken($this->bearerToken)
            ->postJson('/api/admin/evenements', [
                'ec_id'       => $this->ecTermine->id,
                'filiere_id'  => $this->filiere->id,
                'annee_id'    => $this->annee->id,
                'date'        => today()->format('Y-m-d'),
                'heure_debut' => '08:00',
                'heure_fin'   => '10:00',
                'salle'       => 'Salle Test',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
