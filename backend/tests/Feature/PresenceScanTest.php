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

    /**
     * Calcule le scan_challenge attendu pour un device fingerprint donné.
     */
    private function scanChallenge(string $deviceFingerprint): string
    {
        return hash('sha256', $deviceFingerprint . ':' . (Config::get('app.key') ?? 'uac-presence-secret'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Désactiver le rate limiter pour les tests
        $this->withoutMiddleware(ThrottleRequests::class);

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
            'heure_fin'   => Carbon::now()->addHour()->format('H:i:s'),
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

    /**
     * Test 1 : Scan valide — doit retourner 201 avec les données de présence.
     */
    public function test_scan_valide(): void
    {
        $response = $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $this->etudiant->identifiant_unique,
            'token'              => $this->token,
            'device_fingerprint' => 'device-abc-123',
            'scan_challenge'     => $this->scanChallenge('device-abc-123'),
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
            'scan_challenge'     => $this->scanChallenge('device-abc-123'),
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
            'scan_challenge'     => $this->scanChallenge('device-xyz-789'),
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
            'scan_challenge'     => $this->scanChallenge('device-premier-001'),
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
            'scan_challenge'     => $this->scanChallenge('device-frauduleux-999'),
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
}
