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

    /**
     * Régression : sans filtre de date, l'index chargeait TOUTES les séances
     * jamais créées — un établissement de quelques semestres en a des
     * milliers. Elle est désormais paginée, comme students et presence/history.
     */
    public function test_la_liste_des_evenements_est_paginee(): void
    {
        foreach (range(1, 25) as $i) {
            Evenement::create([
                'ec_id'       => $this->ec->id,
                'filiere_id'  => $this->filiere->id,
                'annee_id'    => $this->annee->id,
                'date'        => today()->addDays($i)->format('Y-m-d'),
                'heure_debut' => '08:00',
                'heure_fin'   => '10:00',
                'salle'       => "Salle {$i}",
            ]);
        }

        $reponse = $this->withToken($this->bearerToken)
            ->getJson('/api/admin/evenements?per_page=10');

        $reponse->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 10);

        $page2 = $this->withToken($this->bearerToken)
            ->getJson('/api/admin/evenements?per_page=10&page=2')
            ->assertStatus(200)
            ->assertJsonCount(10, 'data');

        // Aucun événement de la page 1 ne réapparaît sur la page 2.
        $idsPage1 = $reponse->json('data.*.id');
        $idsPage2 = $page2->json('data.*.id');
        $this->assertEmpty(array_intersect($idsPage1, $idsPage2));
    }

    /** per_page est plafonné, comme pour students et presence/history : sans borne, ?per_page=100000 reviendrait à tout charger. */
    public function test_per_page_est_plafonne_a_cent(): void
    {
        $reponse = $this->withToken($this->bearerToken)
            ->getJson('/api/admin/evenements?per_page=100000');

        $reponse->assertStatus(200)
            ->assertJsonPath('meta.per_page', 100);
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

        // Suppression douce : les événements portent SoftDeletes, la ligne
        // subsiste avec sa date de suppression. C'est voulu — un événement
        // supprimé reste une pièce justificative des présences déjà enregistrées.
        $this->assertSoftDeleted('evenements', ['id' => $evenement->id]);
    }

    /**
     * Un EC dont le volume est fait ne reçoit plus de cours ; une évaluation, qui
     * ne consomme rien, se programme encore. Le statut n'est plus une colonne
     * qu'une commande nocturne tenait à jour : il suit les séances terminées.
     */
    public function test_creation_evenement_rejetee_si_ec_termine(): void
    {
        $this->ecTermine->evenements()->create([
            'filiere_id' => $this->filiere->id, 'annee_id' => $this->annee->id,
            'date' => today()->subDays(3)->toDateString(), 'heure_debut' => '08:00:00', 'heure_fin' => '18:00:00',
            'salle' => 'Salle Test', 'statut' => 'termine',
        ]);
        $this->assertSame('termine', $this->ecTermine->fresh()->statut);

        $seance = [
            'ec_id'       => $this->ecTermine->id,
            'date'        => today()->format('Y-m-d'),
            'heure_debut' => '08:00',
            'heure_fin'   => '10:00',
            'salle'       => 'Salle Test',
        ];

        $this->withToken($this->bearerToken)->postJson('/api/admin/evenements', $seance)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->withToken($this->bearerToken)->postJson('/api/admin/evenements', ['type_cours' => 'evaluation'] + $seance)
            ->assertStatus(201);
    }
}
