<?php

namespace Tests\Feature;

use App\Models\Anomaly;
use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\QrCode;
use App\Models\Ue;
use Carbon\Carbon;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests de bout en bout pour le scan de présence (US04 / US06).
 *
 * Couvre 4 scénarios critiques :
 * 1. Scan valide
 * 2. QR Code expiré
 * 3. Mauvais étudiant / filière
 * 4. Tentative de fraude (double scan, device différent)
 */
class PresenceScanTest extends TestCase
{

    private Filiere $filiere;
    private AnneeAcademique $annee;
    private Ec $ec;
    private Evenement $evenement;
    private Etudiant $etudiant;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Désactiver le rate limiter pour les tests
        $this->withoutMiddleware(ThrottleRequests::class);

        // Horloge figée : la fenêtre de présence est ancrée sur l'heure de fin
        // du cours (config/presence.php), donc les fixtures doivent placer
        // « maintenant » dans cette fenêtre de façon déterministe — sinon la
        // suite échoue selon l'heure à laquelle on la lance.
        Carbon::setTestNow(today()->setTime(10, 0));

        // Création des dépendances de base
        $this->filiere = Filiere::create([
            'code'     => 'TEST',
            'intitule' => 'Filière Test',
            'niveau'   => 'L3',
        ]);

        $this->annee = AnneeAcademique::create([
            'libelle'    => '2025-2026',
            'date_debut' => '2025-10-01',
            'date_fin'   => '2026-09-30',
            'active'     => true,
        ]);

        $ue = Ue::create([
            'code'           => 'UE-TEST',
            'intitule'       => 'Unité d\'Enseignement Test',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 30,
        ]);

        $this->ec = Ec::create([
            'ue_id'          => $ue->id,
            'code'           => 'EC-TEST',
            'intitule'       => 'Élément Constitutif Test',
            'volume_horaire' => 30,
        ]);

        $this->evenement = Evenement::create([
            'ec_id'       => $this->ec->id,
            'filiere_id'  => $this->filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => today()->format('Y-m-d'),
            'heure_debut' => Carbon::now()->subHour()->format('H:i:s'),
            // Fin proche : « maintenant » tombe dans la fenêtre [fin − 15 min,
            // fin + 10 min]. Une fin dans une heure placerait le scan avant
            // l'ouverture de la fenêtre.
            'heure_fin'   => Carbon::now()->addMinutes(5)->format('H:i:s'),
            'salle'       => 'Salle Test',
            'statut'      => 'en_cours',
        ]);

        $this->etudiant = Etudiant::create([
            'id'                => (string) Str::uuid(),
            'nom'               => 'DUPONT',
            'prenom'            => 'JEAN',
            'matricule'         => 'TEST-001',
            'filiere_id'        => $this->filiere->id,
            'annee_id'          => $this->annee->id,
            'email'             => 'jean.dupont@test.com',
            'identifiant_unique' => 'DUPONT_JEAN_TEST-001_TEST_L3',
        ]);

        // QR Code valide
        $this->token = (string) Str::uuid();
        QrCode::create([
            'evenement_id' => $this->evenement->id,
            'token'        => $this->token,
            'expire_at'    => Carbon::now()->addMinutes(5),
            'actif'        => true,
        ]);

        // Inscrire l'étudiant à l'EC (table pivot — CDC 7.2.3)
        $this->etudiant->ecs()->syncWithoutDetaching([
            $this->ec->id => ['annee_id' => $this->annee->id],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Test 1 : Scan valide — doit retourner 201 avec les données de présence.
     */
    public function test_scan_valide(): void
    {
        $response = $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $this->etudiant->identifiant_unique,
            'token'              => $this->token,
            'device_fingerprint' => 'device-abc-123',
            'scan_challenge'     => $this->defiDeScan($this->token),
            'latitude'           => 6.3608,
            'longitude'          => 2.4354,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['etudiant', 'matricule', 'heure', 'cours'],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.matricule', 'TEST-001');

        // Vérifier que la présence est bien enregistrée en base
        $this->assertDatabaseHas('presences', [
            'etudiant_id'       => $this->etudiant->id,
            'evenement_id'      => $this->evenement->id,
            'device_fingerprint' => 'device-abc-123',
            'statut'            => 'valide',
        ]);
    }

    /**
     * Test 2 : QR Code expiré — doit retourner 410 (Gone).
     */
    public function test_qr_code_expire(): void
    {
        // Créer un second QR code expiré
        $expiredToken = (string) Str::uuid();
        QrCode::create([
            'evenement_id' => $this->evenement->id,
            'token'        => $expiredToken,
            'expire_at'    => Carbon::now()->subMinutes(10),
            'actif'        => true,
        ]);

        $response = $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $this->etudiant->identifiant_unique,
            'token'              => $expiredToken,
            'device_fingerprint' => 'device-abc-123',
            'scan_challenge'     => $this->defiDeScan($this->token),
        ]);

        $response->assertStatus(410)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'QR Code expiré ou invalide. Veuillez rescanner.');
    }

    /**
     * Test 3 : Mauvais étudiant / filière — doit retourner 403.
     */
    public function test_mauvaise_filiere(): void
    {
        // Créer une autre filière et un étudiant dans cette filière
        $autreFiliere = Filiere::create([
            'code'     => 'AUTRE',
            'intitule' => 'Autre Filière',
            'niveau'   => 'L3',
        ]);

        $autreEtudiant = Etudiant::create([
            'id'                => (string) Str::uuid(),
            'nom'               => 'MARTIN',
            'prenom'            => 'SOPHIE',
            'matricule'         => 'TEST-002',
            'filiere_id'        => $autreFiliere->id,
            'annee_id'          => $this->annee->id,
            'email'             => 'sophie.martin@test.com',
            'identifiant_unique' => 'MARTIN_SOPHIE_TEST-002_AUTRE_L3',
        ]);

        $response = $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $autreEtudiant->identifiant_unique,
            'token'              => $this->token,
            'device_fingerprint' => 'device-xyz-789',
            'scan_challenge'     => $this->defiDeScan($this->token),
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Étudiant non inscrit à ce cours.');
    }

    /**
     * Test 4 : Double scan avec un device différent — doit créer une anomalie.
     */
    public function test_tentative_fraude_double_scan(): void
    {
        // Premier scan valide
        $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $this->etudiant->identifiant_unique,
            'token'              => $this->token,
            'device_fingerprint' => 'device-premier-001',
            'scan_challenge'     => $this->defiDeScan($this->token),
        ])->assertStatus(201);

        // Créer un nouveau QR code pour un deuxième scan (le premier a été invalidé)
        $secondToken = (string) Str::uuid();
        QrCode::create([
            'evenement_id' => $this->evenement->id,
            'token'        => $secondToken,
            'expire_at'    => Carbon::now()->addMinutes(5),
            'actif'        => true,
        ]);

        // Deuxième scan avec un device DIFFÉRENT → fraude
        $response = $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $this->etudiant->identifiant_unique,
            'token'              => $secondToken,
            'device_fingerprint' => 'device-frauduleux-999',
            'scan_challenge'     => $this->defiDeScan($secondToken),
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Alerte fraude : présence déjà enregistrée depuis un autre appareil.');

        // Vérifier qu'une anomalie a été créée
        $this->assertDatabaseHas('anomalies', [
            'etudiant_id' => $this->etudiant->id,
            'type'        => 'double_scan_device_mismatch',
            'severity'    => 'high',
        ]);

        // Vérifier que la présence d'origine reste 'valide' (elle n'est PAS marquée suspect)
        // Seule la tentative frauduleuse est bloquée ; la première présence légitime conserve son statut.
        $this->assertDatabaseHas('presences', [
            'etudiant_id'       => $this->etudiant->id,
            'device_fingerprint' => 'device-premier-001',
            'statut'            => 'valide',
        ]);
    }

    // =====================================================================
    // Contrat client <-> serveur sur scan_challenge
    //
    // Ces deux tests sont les seuls de la suite a ne PAS recalculer la formule
    // du serveur. Ils partent de ce qu'un vrai client obtient reellement, et
    // c'est precisement ce qui manquait : la suite pouvait etre entierement
    // verte alors qu'aucun client existant ne parvenait a faire valider un
    // scan.
    // =====================================================================

    public function test_le_defi_emis_par_le_serveur_est_accepte_au_scan(): void
    {
        // Etape 1 — le client ouvre l'URL du QR Code et lit les infos du cours.
        $infos = $this->getJson('/api/presence/course-by-token/' . $this->token)
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey(
            'scan_challenge',
            $infos,
            "Le client n'a aucun moyen d'obtenir un defi valide si l'endpoint ne le fournit pas."
        );
        $this->assertNotEmpty($infos['scan_challenge']);

        // Etape 2 — il soumet le defi tel quel, sans rien recalculer.
        $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $this->etudiant->identifiant_unique,
            'token'              => $this->token,
            'device_fingerprint' => 'device-contrat-001',
            'scan_challenge'     => $infos['scan_challenge'],
            'latitude'           => 6.3608,
            'longitude'          => 2.4354,
        ])->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('presences', [
            'etudiant_id'  => $this->etudiant->id,
            'evenement_id' => $this->evenement->id,
            'statut'       => 'valide',
        ]);
    }

    public function test_un_defi_fabrique_par_le_client_est_refuse(): void
    {
        // Un defi derive d'un secret cote client — la conception precedente —
        // doit etre rejete, et laisser une trace exploitable.
        $defiForge = hash('sha256', 'device-contrat-002:uac-presence-secret');

        $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $this->etudiant->identifiant_unique,
            'token'              => $this->token,
            'device_fingerprint' => 'device-contrat-002',
            'scan_challenge'     => $defiForge,
            'latitude'           => 6.3608,
            'longitude'          => 2.4354,
        ])->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('anomalies', [
            'etudiant_id' => $this->etudiant->id,
            'type'        => 'invalid_scan_challenge',
            'severity'    => 'high',
        ]);

        $this->assertDatabaseMissing('presences', [
            'etudiant_id'  => $this->etudiant->id,
            'evenement_id' => $this->evenement->id,
        ]);
    }

    public function test_le_defi_d_un_autre_qr_code_est_refuse(): void
    {
        // Le defi est lie au jeton : celui d'une autre seance ne doit pas passer.
        $autreToken = (string) \Illuminate\Support\Str::uuid();
        \App\Models\QrCode::create([
            'evenement_id' => $this->evenement->id,
            'token'        => $autreToken,
            'expire_at'    => Carbon::now()->addMinutes(5),
            'actif'        => true,
        ]);

        $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $this->etudiant->identifiant_unique,
            'token'              => $this->token,
            'device_fingerprint' => 'device-contrat-003',
            'scan_challenge'     => $this->defiDeScan($autreToken),
            'latitude'           => 6.3608,
            'longitude'          => 2.4354,
        ])->assertStatus(403);
    }
}
