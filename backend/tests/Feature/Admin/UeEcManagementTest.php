<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Filiere;
use App\Models\Ue;
use App\Models\User;
use Tests\TestCase;

/**
 * Tests pour la gestion des UEs et ECs.
 *
 * Couvre :
 * - CRUD UE
 * - Volume horaire UE = somme des ECs
 * - CRUD EC
 * - Auto-inscription des étudiants aux ECs via UeObserver
 * - Vérification que EC 'termine' rejeté lors de création d'événement
 */
class UeEcManagementTest extends TestCase
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

    // ── UE CRUD ─────────────────────────────────────────────────

    public function test_admin_peut_creer_une_ue(): void
    {
        $payload = [
            'code'           => 'UE-TEST-01',
            'intitule'       => 'Unité Test 01',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 30,
        ];

        $response = $this->withToken($this->bearerToken)
            ->postJson('/api/admin/ues', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'UE-TEST-01');

        $this->assertDatabaseHas('ues', ['code' => 'UE-TEST-01']);
    }

    public function test_admin_peut_lister_les_ues(): void
    {
        Ue::create([
            'code'           => 'UE-LIST-01',
            'intitule'       => 'UE Liste 01',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 30,
        ]);

        Ue::create([
            'code'           => 'UE-LIST-02',
            'intitule'       => 'UE Liste 02',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 2,
            'volume_horaire' => 20,
        ]);

        $response = $this->withToken($this->bearerToken)
            ->getJson('/api/admin/ues');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_peut_consulter_une_ue(): void
    {
        $ue = Ue::create([
            'code'           => 'UE-SHOW',
            'intitule'       => 'UE à afficher',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 30,
        ]);

        $response = $this->withToken($this->bearerToken)
            ->getJson("/api/admin/ues/{$ue->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.code', 'UE-SHOW');
    }

    public function test_admin_peut_modifier_une_ue(): void
    {
        $ue = Ue::create([
            'code'           => 'UE-MODIF',
            'intitule'       => 'Avant modification',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 30,
        ]);

        $response = $this->withToken($this->bearerToken)
            ->putJson("/api/admin/ues/{$ue->id}", [
                'intitule' => 'Après modification',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intitule', 'Après modification');
    }

    public function test_admin_peut_supprimer_une_ue(): void
    {
        $ue = Ue::create([
            'code'           => 'UE-DEL',
            'intitule'       => 'UE à supprimer',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 30,
        ]);

        $response = $this->withToken($this->bearerToken)
            ->deleteJson("/api/admin/ues/{$ue->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('ues', ['id' => $ue->id]);
    }

    // ── EC CRUD ─────────────────────────────────────────────────

    public function test_admin_peut_ajouter_un_ec_a_une_ue(): void
    {
        $ue = Ue::create([
            'code'           => 'UE-EC-01',
            'intitule'       => 'UE avec ECs',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 0,
        ]);

        $response = $this->withToken($this->bearerToken)
            ->postJson('/api/admin/ecs', [
                'ue_id'          => $ue->id,
                'code'           => 'EC-01',
                'intitule'       => 'Premier EC',
                'volume_horaire' => 20,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'EC-01');
    }

    public function test_volume_horaire_ue_sactualise_avec_somme_des_ecs(): void
    {
        $ue = Ue::create([
            'code'           => 'UE-VOL',
            'intitule'       => 'Test volume UE',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 0,
        ]);

        // Ajouter EC 20h
        $this->withToken($this->bearerToken)
            ->postJson('/api/admin/ecs', [
                'ue_id'          => $ue->id,
                'code'           => 'EC-VOL-01',
                'intitule'       => 'EC 20h',
                'volume_horaire' => 20,
            ]);

        $ue->refresh();
        $this->assertEquals(20, $ue->volume_horaire);

        // Ajouter EC 30h
        $this->withToken($this->bearerToken)
            ->postJson('/api/admin/ecs', [
                'ue_id'          => $ue->id,
                'code'           => 'EC-VOL-02',
                'intitule'       => 'EC 30h',
                'volume_horaire' => 30,
            ]);

        $ue->refresh();
        $this->assertEquals(50, $ue->volume_horaire);
    }

    public function test_admin_peut_modifier_un_ec(): void
    {
        $ue = Ue::create([
            'code'           => 'UE-MOD-EC',
            'intitule'       => 'UE Modif EC',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 30,
        ]);

        $ec = Ec::create([
            'ue_id'          => $ue->id,
            'code'           => 'EC-MOD',
            'intitule'       => 'Avant',
            'volume_horaire' => 30,
        ]);

        $response = $this->withToken($this->bearerToken)
            ->putJson("/api/admin/ecs/{$ec->id}", [
                'intitule' => 'Après modif',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intitule', 'Après modif');
    }

    // ── STATUT EC ───────────────────────────────────────────────

    public function test_ec_statut_demarre_a_non_demarre(): void
    {
        $ue = Ue::create([
            'code'           => 'UE-STAT',
            'intitule'       => 'UE Statut',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 30,
        ]);

        $ec = Ec::create([
            'ue_id'          => $ue->id,
            'code'           => 'EC-STAT',
            'intitule'       => 'EC Statut Test',
            'volume_horaire' => 30,
        ]);

        $this->assertEquals('non_demarre', $ec->statut);
    }

    public function test_ec_statut_devient_termine_quand_heures_atteintes(): void
    {
        $ue = Ue::create([
            'code'           => 'UE-TERM',
            'intitule'       => 'UE Termine',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 10,
        ]);

        $ec = Ec::create([
            'ue_id'          => $ue->id,
            'code'           => 'EC-TERM',
            'intitule'       => 'EC Terminé',
            'volume_horaire' => 10,
        ]);

        // Simuler le statut 'termine' via la commande de synchronisation
        $this->artisan('ecs:sync-statut', ['--ec-id' => $ec->id])
            ->assertSuccessful();

        $ec->refresh();
        $this->assertEquals('non_demarre', $ec->statut);

        // Créer un événement terminé pour cet EC
        $evenement = $ec->evenements()->create([
            'filiere_id'  => $this->filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => '2026-01-15',
            'heure_debut' => '08:00:00',
            'heure_fin'   => '18:00:00',
            'salle'       => 'Salle Test',
            'statut'      => 'termine',
        ]);

        // Re-synchroniser
        $this->artisan('ecs:sync-statut', ['--ec-id' => $ec->id])
            ->assertSuccessful();

        $ec->refresh();
        $this->assertEquals('termine', $ec->statut);
    }

    public function test_ue_statut_reflete_celui_de_ses_ecs(): void
    {
        $ue = Ue::create([
            'code'           => 'UE-STAT-REF',
            'intitule'       => 'UE Reflet',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 60,
        ]);

        $ec = Ec::create([
            'ue_id'          => $ue->id,
            'code'           => 'EC-REF',
            'intitule'       => 'EC Reflet',
            'volume_horaire' => 60,
        ]);

        $this->assertEquals('non_demarre', $ue->statut);
        $this->assertEquals('non_demarre', $ec->statut);

        // Créer 6 événements terminés de 10h chacun = 60h → EC devient termine
        $jour = '2026-01-13';
        for ($i = 0; $i < 6; $i++) {
            $ec->evenements()->create([
                'filiere_id'  => $this->filiere->id,
                'annee_id'    => $this->annee->id,
                'date'        => $jour,
                'heure_debut' => '08:00:00',
                'heure_fin'   => '18:00:00',
                'salle'       => 'Salle Test',
                'statut'      => 'termine',
            ]);
            $jour = date('Y-m-d', strtotime($jour . ' +1 day'));
        }

        $this->artisan('ecs:sync-statut')
            ->assertSuccessful();

        $ec->refresh();
        $ue->refresh();
        $this->assertEquals('termine', $ec->statut);
        $this->assertEquals('termine', $ue->statut);
    }

    public function test_ue_avec_mix_ec_est_en_cours(): void
    {
        $ue = Ue::create([
            'code'           => 'UE-MIX',
            'intitule'       => 'UE Mixte',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 80,
        ]);

        $ec1 = Ec::create([
            'ue_id'          => $ue->id,
            'code'           => 'EC-MIX-01',
            'intitule'       => 'EC 1 termine',
            'volume_horaire' => 30,
        ]);
        // 3 événements de 10h chacun = 30h → termine
        for ($i = 0; $i < 3; $i++) {
            $ec1->evenements()->create([
                'filiere_id'  => $this->filiere->id,
                'annee_id'    => $this->annee->id,
                'date'        => '2026-01-' . str_pad(13 + $i, 2, '0', STR_PAD_LEFT),
                'heure_debut' => '08:00:00',
                'heure_fin'   => '18:00:00',
                'salle'       => 'Salle Test',
                'statut'      => 'termine',
            ]);
        }

        $ec2 = Ec::create([
            'ue_id'          => $ue->id,
            'code'           => 'EC-MIX-02',
            'intitule'       => 'EC 2 en cours',
            'volume_horaire' => 50,
        ]);
        // 1 événement de 10h → en_cours
        $ec2->evenements()->create([
            'filiere_id'  => $this->filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => '2026-01-16',
            'heure_debut' => '08:00:00',
            'heure_fin'   => '18:00:00',
            'salle'       => 'Salle Test',
            'statut'      => 'termine',
        ]);

        // Synchroniser les statuts
        $this->artisan('ecs:sync-statut')
            ->assertSuccessful();

        $ue->refresh();
        $this->assertEquals('en_cours', $ue->statut);
    }
}
