<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\QrCode;
use App\Models\Salle;
use App\Models\Ue;
use Carbon\Carbon;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Test de la vérification triple facteur (CDC US04 & US06).
 *
 * Couvre la géolocalisation GPS, le WiFi (SSID/BSSID), et le mode dégradé.
 */
class TripleFactorScanTest extends TestCase
{

    private Etablissement $etablissement;
    private Filiere $filiere;
    private AnneeAcademique $annee;
    private Ec $ec;
    private Evenement $evenement;
    private Etudiant $etudiant;
    private Salle $salle;
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

        // Désactiver le rate limiter pour les tests (3 req/min bloque les tests rapides)
        $this->withoutMiddleware(ThrottleRequests::class);

        // 0. Établissement
        $this->etablissement = Etablissement::create([
            'code'  => 'IFRI',
            'nom'   => 'Institut de Formation et de Recherche en Informatique',
            'email' => 'contact@ifri.uac.bj',
            'actif' => true,
        ]);

        // 1. Filière
        $this->filiere = Filiere::create([
            'code'     => 'GLT',
            'intitule' => 'Génie Logiciel',
            'niveau'   => 'L3',
        ]);

        // 2. Année académique
        $this->annee = AnneeAcademique::create([
            'libelle'    => '2025-2026',
            'date_debut' => '2025-10-01',
            'date_fin'   => '2026-09-30',
            'active'     => true,
        ]);

        // 3. UE
        $ue = Ue::create([
            'code'           => 'UE-DEVWEB',
            'intitule'       => 'Développement Web Avancé',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 30,
        ]);

        // 4. EC
        $this->ec = Ec::create([
            'ue_id'          => $ue->id,
            'code'           => 'EC-FRONT',
            'intitule'       => 'Développement Frontend',
            'volume_horaire' => 15,
        ]);

        // 5. SALLE avec géolocalisation + WiFi
        // Coordonnées IFRI, Cotonou
        $this->salle = Salle::create([
            'etablissement_id' => $this->etablissement->id,
            'nom'              => 'Salle TP 101',
            'code'             => 'TP101',
            'latitude'         => 6.3608,
            'longitude'        => 2.4354,
            'rayon_geofence_m' => 100,
            'ssid_attendu'     => 'ASIN-STAFF',
            'bssid_attendu'    => '20:58:69:69:ac:7c',
            'ip_range'         => '10.53.8.0/24',
            'hors_reseau'      => false,
            'actif'            => true,
        ]);

        // 6. Événement lié à la salle
        $this->evenement = Evenement::create([
            'ec_id'       => $this->ec->id,
            'filiere_id'  => $this->filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => today()->format('Y-m-d'),
            'heure_debut' => Carbon::now()->subHour()->format('H:i:s'),
            'heure_fin'   => Carbon::now()->addHour()->format('H:i:s'),
            'salle'       => 'Salle TP 101',
            'salle_id'    => $this->salle->id,
            'statut'      => 'en_cours',
        ]);

        // 7. Étudiant
        $this->etudiant = Etudiant::create([
            'id'                 => (string) Str::uuid(),
            'nom'                => 'DUPONT',
            'prenom'             => 'JEAN',
            'matricule'          => '22A1234',
            'filiere_id'         => $this->filiere->id,
            'annee_id'           => $this->annee->id,
            'email'              => 'jean.dupont@ifri.uac.bj',
            'identifiant_unique' => 'DUPONT_JEAN_22A1234_GLT_L3',
        ]);

        // 8. QR Code valide
        $this->token = (string) Str::uuid();
        QrCode::create([
            'evenement_id' => $this->evenement->id,
            'token'        => $this->token,
            'expire_at'    => Carbon::now()->addMinutes(5),
            'actif'        => true,
        ]);

        // 9. Inscrire l'étudiant à l'EC
        $this->etudiant->ecs()->syncWithoutDetaching([
            $this->ec->id => ['annee_id' => $this->annee->id],
        ]);
    }

    // ─── SCÉNARIOS GPS ───────────────────────────────────────

    /**
     * Test 1 : GPS valide (dans le rayon) + WiFi valide → scan OK.
     */
    public function test_geolocalisation_et_wifi_valides(): void
    {
        $response = $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $this->etudiant->identifiant_unique,
            'token'              => $this->token,
            'device_fingerprint' => 'device-abc-123',
            'scan_challenge'     => $this->scanChallenge('device-abc-123'),
            'latitude'           => 6.3608,
            'longitude'          => 2.4354,
            'ssid'               => 'ASIN-STAFF',
            'bssid'              => '20:58:69:69:ac:7c',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.matricule', '22A1234');
    }

    /**
     * Test 2 : GPS hors rayon (> 100m) + WiFi valide → refusé.
     */
    public function test_gps_hors_zone(): void
    {
        // Coordonnées à ~1.5 km d'IFRI (Ganhi, Cotonou)
        $response = $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $this->etudiant->identifiant_unique,
            'token'              => $this->token,
            'device_fingerprint' => 'device-abc-123',
            'scan_challenge'     => $this->scanChallenge('device-abc-123'),
            'latitude'           => 6.3720,
            'longitude'          => 2.4220,
            'ssid'               => 'ASIN-STAFF',
            'bssid'              => '20:58:69:69:ac:7c',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', fn (string $msg) =>
                str_contains($msg, 'GPS') && str_contains($msg, 'hors zone')
            );
    }

    /**
     * Test 3 : GPS valide + WiFi erroné (mauvais SSID) → refusé.
     */
    public function test_wifi_erreur(): void
    {
        $response = $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $this->etudiant->identifiant_unique,
            'token'              => $this->token,
            'device_fingerprint' => 'device-abc-123',
            'scan_challenge'     => $this->scanChallenge('device-abc-123'),
            'latitude'           => 6.3608,
            'longitude'          => 2.4354,
            'ssid'               => 'CAFE-NEIGHBOUR',
            'bssid'              => '00:11:22:33:44:55',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', fn (string $msg) =>
                str_contains($msg, 'WiFi') && str_contains($msg, 'non conforme')
            );
    }

    /**
     * Test 4 : GPS + WiFi tous deux invalides → refusé avec les deux raisons.
     */
    public function test_gps_et_wifi_invalides(): void
    {
        $response = $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $this->etudiant->identifiant_unique,
            'token'              => $this->token,
            'device_fingerprint' => 'device-abc-123',
            'scan_challenge'     => $this->scanChallenge('device-abc-123'),
            'latitude'           => 6.3720,
            'longitude'          => 2.4220,
            'ssid'               => 'CAFE-NEIGHBOUR',
            'bssid'              => '00:11:22:33:44:55',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', fn (string $msg) =>
                str_contains($msg, 'GPS') && str_contains($msg, 'WiFi')
            );
    }

    /**
     * Test 5 : GPS non fourni (null) alors que la salle l'exige → refusé.
     */
    public function test_gps_non_fourni(): void
    {
        $response = $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $this->etudiant->identifiant_unique,
            'token'              => $this->token,
            'device_fingerprint' => 'device-abc-123',
            'scan_challenge'     => $this->scanChallenge('device-abc-123'),
            'ssid'               => 'ASIN-STAFF',
            'bssid'              => '20:58:69:69:ac:7c',
        ]);

        // latitude=null → isWithinGeofence(null, 2.4354) → distance=null → false
        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    // ─── SCÉNARIOS MODE DÉGRADÉ ─────────────────────────────

    /**
     * Test 6 : Salle en mode "hors réseau" → seule la géolocalisation est requise, pas le WiFi.
     */
    public function test_salle_hors_reseau(): void
    {
        // Créer une autre salle en mode dégradé
        $salleDegrade = Salle::create([
            'etablissement_id' => $this->etablissement->id,
            'nom'              => 'Amphi 200',
            'code'             => 'AMP200',
            'latitude'         => 6.3608,
            'longitude'        => 2.4354,
            'rayon_geofence_m' => 100,
            'hors_reseau'      => true,
            'actif'            => true,
        ]);

        $eventDegrade = Evenement::create([
            'ec_id'       => $this->ec->id,
            'filiere_id'  => $this->filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => today()->format('Y-m-d'),
            'heure_debut' => Carbon::now()->subHour()->format('H:i:s'),
            'heure_fin'   => Carbon::now()->addHour()->format('H:i:s'),
            'salle'       => 'Amphi 200',
            'salle_id'    => $salleDegrade->id,
            'statut'      => 'en_cours',
        ]);

        $tokenDegrade = (string) Str::uuid();
        QrCode::create([
            'evenement_id' => $eventDegrade->id,
            'token'        => $tokenDegrade,
            'expire_at'    => Carbon::now()->addMinutes(5),
            'actif'        => true,
        ]);

        // On envoie sans SSID/BSSID → doit passer car la salle est hors_reseau
        $response = $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $this->etudiant->identifiant_unique,
            'token'              => $tokenDegrade,
            'device_fingerprint' => 'device-degrade-001',
            'scan_challenge'     => $this->scanChallenge('device-degrade-001'),
            'latitude'           => 6.3608,
            'longitude'          => 2.4354,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // ─── SCÉNARIOS SALLE INACTIVE ───────────────────────────

    /**
     * Test 7 : Salle inactive → mode basique (QR code seul), pas de vérif GPS/WiFi.
     */
    public function test_salle_inactive(): void
    {
        $this->salle->update(['actif' => false]);

        $tokenInactif = (string) Str::uuid();
        QrCode::create([
            'evenement_id' => $this->evenement->id,
            'token'        => $tokenInactif,
            'expire_at'    => Carbon::now()->addMinutes(5),
            'actif'        => true,
        ]);

        // Salle inactive → pas de vérif GPS/WiFi
        $response = $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $this->etudiant->identifiant_unique,
            'token'              => $tokenInactif,
            'device_fingerprint' => 'device-inactif-001',
            'scan_challenge'     => $this->scanChallenge('device-inactif-001'),
            // Pas de GPS, pas de WiFi → doit passer quand même
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // ─── SCÉNARIO AUTHENTIFICATION 2FA DU COURS (QR) ────────

    /**
     * Test 8 : Vérifier que le endpoint course-by-token renvoie les infos de vérification.
     */
    public function test_course_by_token_retourne_verifications(): void
    {
        $response = $this->getJson("/api/presence/course-by-token/{$this->token}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'verification' => [
                        'gps_requis',
                        'wifi_requis',
                        'nom_salle',
                    ],
                ],
            ]);

        $verif = $response->json('data.verification');
        $this->assertTrue($verif['gps_requis']);
        $this->assertTrue($verif['wifi_requis']);
        $this->assertEquals('Salle TP 101', $verif['nom_salle']);
    }

    /**
     * Test 9 : Même BSSID mais avec des casses différentes → toléré.
     */
    public function test_wifi_bssid_insensible_a_la_casse(): void
    {
        $response = $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $this->etudiant->identifiant_unique,
            'token'              => $this->token,
            'device_fingerprint' => 'device-case-001',
            'scan_challenge'     => $this->scanChallenge('device-case-001'),
            'latitude'           => 6.3608,
            'longitude'          => 2.4354,
            'ssid'               => 'asin-staff',
            'bssid'              => '20:58:69:69:AC:7C',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // ─── NETTOYAGE ──────────────────────────────────────────

    protected function tearDown(): void
    {
        // Réactiver la salle pour les tests suivants si modifiée
        Salle::where('id', $this->salle->id)->update(['actif' => true]);
        parent::tearDown();
    }
}
