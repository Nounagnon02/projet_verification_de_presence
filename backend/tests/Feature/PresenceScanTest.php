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
        $response = $this->withToken($this->jetonDeScan($this->etudiant))->postJson('/api/presence/scan', [
            'token'              => $this->token,
            'device_fingerprint' => 'device-abc-123',
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

        $response = $this->withToken($this->jetonDeScan($this->etudiant))->postJson('/api/presence/scan', [
            'token'              => $expiredToken,
            'device_fingerprint' => 'device-abc-123',
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

        $response = $this->withToken($this->jetonDeScan($autreEtudiant))->postJson('/api/presence/scan', [
            'token'              => $this->token,
            'device_fingerprint' => 'device-xyz-789',
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
        $this->withToken($this->jetonDeScan($this->etudiant))->postJson('/api/presence/scan', [
            'token'              => $this->token,
            'device_fingerprint' => 'device-premier-001',
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
        $response = $this->withToken($this->jetonDeScan($this->etudiant))->postJson('/api/presence/scan', [
            'token'              => $secondToken,
            'device_fingerprint' => 'device-frauduleux-999',
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
    // Contrat : le scan exige un jeton étudiant, qui seul désigne l'étudiant
    //
    // Avant l'authentification du scan, ces deux tests vérifiaient un défi
    // cryptographique émis par le serveur puis renvoyé tel quel — un mécanisme
    // qui n'authentifiait rien de plus qu'un jeton QR déjà valide. Le scan
    // exige désormais un jeton Sanctum de capacité « etudiant » : c'est lui,
    // et lui seul, qui désigne l'étudiant.
    // =====================================================================

    public function test_scan_sans_jeton_est_refuse(): void
    {
        $this->postJson('/api/presence/scan', [
            'token'              => $this->token,
            'device_fingerprint' => 'device-sans-jeton',
            'latitude'           => 6.3608,
            'longitude'          => 2.4354,
        ])->assertStatus(401);

        $this->assertDatabaseMissing('presences', [
            'evenement_id' => $this->evenement->id,
        ]);
    }

    public function test_le_jeton_designe_l_etudiant_quel_que_soit_le_corps_de_la_requete(): void
    {
        // Régression : l'étudiant était identifié par « identifiant_unique »
        // posté dans le corps — une chaîne déterministe, reconstituable par
        // n'importe quel camarade de promotion. Un champ du même nom dans le
        // corps ne doit plus avoir AUCUN effet : seul le jeton compte.
        $response = $this->withToken($this->jetonDeScan($this->etudiant))->postJson('/api/presence/scan', [
            'identifiant_unique' => 'CECI_NE_DESIGNE_PERSONNE',
            'token'              => $this->token,
            'device_fingerprint' => 'device-corps-ignore',
            'latitude'           => 6.3608,
            'longitude'          => 2.4354,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.matricule', $this->etudiant->matricule);
    }

    public static function jetonsMalFormes(): array
    {
        return [
            'chaine quelconque'  => ['pas-un-uuid'],
            'entier'             => ['3564070028177549877'],
            'mot'                => ['token'],
            'injection SQL'      => ["' OR 1=1 --"],
            'uuid tronque'       => ['11111111-2222-3333'],
            'caracteres speciaux' => ['<script>alert(1)</script>'],
        ];
    }

    /**
     * @dataProvider jetonsMalFormes
     */
    public function test_un_jeton_mal_forme_donne_404_et_non_500(string $jeton): void
    {
        $reponse = $this->getJson('/api/presence/course-by-token/' . urlencode($jeton));

        // La propriete qui compte : 404, jamais 500, et aucune trace d'exception.
        $reponse->assertStatus(404)
            ->assertJsonPath('success', false);

        // Deux messages sont acceptables, et aucun n'apprend quoi que ce soit :
        // « QR Code invalide ou expiré. » quand le controleur est atteint, et
        // « Route non trouvée. » quand le routeur rejette la valeur en amont
        // — cas des charges contenant une barre oblique.
        $this->assertContains(
            $reponse->json('message'),
            ['QR Code invalide ou expiré.', 'Route non trouvée.'],
            'Message inattendu : ' . $reponse->json('message'),
        );

        // Aucune fuite de la pile technique dans le corps de la reponse.
        $corps = $reponse->getContent();
        foreach (['SQLSTATE', 'PDOException', 'vendor/laravel', 'uuid:'] as $fuite) {
            $this->assertStringNotContainsString($fuite, $corps);
        }
    }

    public function test_un_jeton_inconnu_mais_bien_forme_donne_aussi_404(): void
    {
        $this->getJson('/api/presence/course-by-token/' . \Illuminate\Support\Str::uuid())
            ->assertStatus(404)
            ->assertJsonPath('message', 'QR Code invalide ou expiré.');
    }

    // =====================================================================
    // Presence supprimee logiquement
    //
    // La contrainte d'unicite SQL porte sur (etudiant_id, evenement_id) sans
    // tenir compte de deleted_at, alors qu'Eloquent exclut par defaut les lignes
    // supprimees. Une presence effacee par un administrateur restait donc
    // invisible au controle de doublon tout en bloquant l'insertion : l'etudiant
    // recevait l'exception PDO brute — nom de la base, hote, port — sur un
    // endpoint public, et ne pouvait plus jamais scanner ce cours.
    //
    // Constate en pilotant l'application dans un navigateur, jamais par la suite
    // de tests : aucun test ne supprimait de presence avant de rescanner.
    // =====================================================================

    public function test_un_rescan_apres_suppression_administrative_est_accepte(): void
    {
        $presence = Presence::create([
            'etudiant_id'        => $this->etudiant->id,
            'evenement_id'       => $this->evenement->id,
            'heure_scan'         => Carbon::now()->subHour(),
            'device_fingerprint' => 'device-avant-suppression',
            'ip_address'         => '10.0.0.1',
            'statut'             => 'valide',
        ]);
        $presence->delete();

        $this->assertSoftDeleted('presences', ['id' => $presence->id]);

        $reponse = $this->withToken($this->jetonDeScan($this->etudiant))->postJson('/api/presence/scan', [
            'token'              => $this->token,
            'device_fingerprint' => 'device-apres-suppression',
            'latitude'           => 6.3608,
            'longitude'          => 2.4354,
        ]);

        $reponse->assertStatus(200)->assertJsonPath('success', true);

        // La ligne est retablie, avec les donnees du NOUVEAU passage.
        $this->assertDatabaseHas('presences', [
            'id'                 => $presence->id,
            'deleted_at'         => null,
            'device_fingerprint' => 'device-apres-suppression',
            'statut'             => 'valide',
        ]);

        // Et une seule ligne, pas deux : l'unicite est preservee.
        $this->assertSame(
            1,
            Presence::withTrashed()
                ->where('etudiant_id', $this->etudiant->id)
                ->where('evenement_id', $this->evenement->id)
                ->count(),
        );
    }

    public function test_aucune_erreur_sql_ne_fuit_vers_le_client(): void
    {
        // Meme scenario, mais on verifie ici ce que LIT l'etudiant : jamais un
        // fragment d'exception de base de donnees.
        $presence = Presence::create([
            'etudiant_id'        => $this->etudiant->id,
            'evenement_id'       => $this->evenement->id,
            'heure_scan'         => Carbon::now()->subHour(),
            'device_fingerprint' => 'device-quelconque',
            'ip_address'         => '10.0.0.1',
            'statut'             => 'valide',
        ]);
        $presence->delete();

        $corps = $this->withToken($this->jetonDeScan($this->etudiant))->postJson('/api/presence/scan', [
            'token'              => $this->token,
            'device_fingerprint' => 'device-autre',
        ])->getContent();

        foreach (['SQLSTATE', 'duplicate key', 'presences_etudiant', 'pgsql', 'Connection:'] as $fuite) {
            $this->assertStringNotContainsString(
                $fuite,
                $corps,
                "La reponse expose « {$fuite} » : une erreur de base de donnees ne doit jamais atteindre le client."
            );
        }
    }

    // =====================================================================
    // Un jeton sert a TOUTE la salle pendant sa fenetre
    //
    // Le jeton etait invalide des le premier scan. Un seul etudiant pouvait donc
    // valider par jeton, les suivants recevant 410 jusqu'a la rotation, qui a
    // lieu chaque minute : un amphitheatre de 500 etudiants aurait demande plus
    // de huit heures.
    //
    // Aucun test ne couvrait ce comportement — le cas E2E-SCAN-02 du plan etait
    // liste mais jamais ecrit. C'est ainsi que le defaut a survecu.
    // =====================================================================

    public function test_un_meme_jeton_sert_a_plusieurs_etudiants(): void
    {
        $camarades = collect(range(1, 4))->map(fn (int $n) => Etudiant::create([
            'nom'                => 'CAMARADE' . $n,
            'prenom'             => 'Prenom' . $n,
            'matricule'          => 'AMPHI-' . $n,
            'email'              => "camarade{$n}@uac.test",
            'filiere_id'         => $this->filiere->id,
            'annee_id'           => $this->annee->id,
            'identifiant_unique' => 'AMPHI_ETUDIANT_' . $n,
        ]));

        foreach ($camarades as $rang => $camarade) {
            $this->withToken($this->jetonDeScan($camarade))->postJson('/api/presence/scan', [
                'token'              => $this->token,
                'device_fingerprint' => 'appareil-personnel-' . $rang,
                'latitude'           => 6.3608,
                'longitude'          => 2.4354,
            ])->assertStatus(201)->assertJsonPath('success', true);
        }

        // Les quatre presences existent, chacune sur son appareil.
        $this->assertSame(
            4,
            Presence::whereIn('etudiant_id', $camarades->pluck('id'))
                ->where('evenement_id', $this->evenement->id)
                ->count(),
        );

        // Et le jeton est toujours exploitable : il vit sa fenetre, pas un scan.
        $this->assertDatabaseHas('qrcodes', ['token' => $this->token, 'actif' => true]);
    }

    public function test_le_jeton_reste_unique_pour_l_evenement(): void
    {
        // La regeneration par scan creait un second jeton actif alors que l'ecran
        // de la salle affichait encore le premier : l'etudiant suivant scannait
        // une image devenue inexploitable. La rotation appartient desormais au
        // seul planificateur.
        $this->withToken($this->jetonDeScan($this->etudiant))->postJson('/api/presence/scan', [
            'token'              => $this->token,
            'device_fingerprint' => 'device-rotation',
            'latitude'           => 6.3608,
            'longitude'          => 2.4354,
        ])->assertStatus(201);

        $this->assertSame(
            1,
            \App\Models\QrCode::where('evenement_id', $this->evenement->id)
                ->where('actif', true)
                ->count(),
            'Un scan ne doit pas creer un second jeton actif.'
        );
    }

    public function test_un_jeton_expire_reste_refuse(): void
    {
        // La duree de vie demeure la vraie protection : un code photographie puis
        // partage est perime avant d'atteindre son destinataire.
        \App\Models\QrCode::where('token', $this->token)
            ->update(['expire_at' => Carbon::now()->subSecond()]);

        $this->withToken($this->jetonDeScan($this->etudiant))->postJson('/api/presence/scan', [
            'token'              => $this->token,
            'device_fingerprint' => 'device-tardif',
        ])->assertStatus(410);
    }
}
