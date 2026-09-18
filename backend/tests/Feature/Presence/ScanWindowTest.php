<?php

namespace Tests\Feature\Presence;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\QrCode;
use App\Models\Ue;
use Carbon\Carbon;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fenêtre de prise de présence — bornes et rotation du QR Code.
 *
 * La fenêtre est ancrée sur l'heure de FIN du cours (config/presence.php) :
 * scan accepté de « fin − 15 min » à « fin + 10 min ». Ces tests verrouillent
 * les quatre bornes, le cas du cours à cheval sur minuit, le plafonnement de
 * l'expiration des tokens, et la rotation effective du QR Code.
 */
class ScanWindowTest extends TestCase
{
    private Evenement $evenement;
    private Etudiant $etudiant;
    private Ec $ec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        Config::set('presence.scan.minutes_avant_fin', 15);
        Config::set('presence.scan.minutes_apres_fin', 10);
        Config::set('presence.qr.ttl_secondes', 60);

        $filiere = Filiere::create([
            'code'     => 'FEN-' . Str::random(5),
            'intitule' => 'Filière Fenêtre',
            'niveau'   => 'L3',
        ]);

        // Libellé volontairement distinct de celui des seeders et année non
        // active : ce test ne doit pas entrer en conflit d'unicité selon que la
        // base de test a été semée ou non. Le chemin de scan ne consulte pas le
        // drapeau « active ».
        $annee = AnneeAcademique::create([
            'libelle'    => '2098-2099 (fenêtre)',
            'date_debut' => '2098-10-01',
            'date_fin'   => '2099-09-30',
            'active'     => false,
        ]);

        $ue = Ue::create([
            'code'           => 'UE-FEN',
            'intitule'       => 'UE Fenêtre',
            'filiere_id'     => $filiere->id,
            'annee_id'       => $annee->id,
            'semestre'       => 1,
            'volume_horaire' => 30,
        ]);

        $this->ec = Ec::create([
            'ue_id'          => $ue->id,
            'code'           => 'EC-FEN',
            'intitule'       => 'EC Fenêtre',
            'volume_horaire' => 30,
        ]);

        // Cours de 08:00 à 10:00 → fenêtre de scan attendue : 09:45 → 10:10.
        $this->evenement = Evenement::create([
            'ec_id'       => $this->ec->id,
            'filiere_id'  => $filiere->id,
            'annee_id'    => $annee->id,
            'date'        => today()->format('Y-m-d'),
            'heure_debut' => '08:00:00',
            'heure_fin'   => '10:00:00',
            'salle'       => 'Salle Fenêtre',
            'statut'      => 'planifie',
        ]);

        $this->etudiant = Etudiant::create([
            'id'                 => (string) Str::uuid(),
            'nom'                => 'AYIVI',
            'prenom'             => 'KOFFI',
            'matricule'          => 'FEN-001',
            'filiere_id'         => $filiere->id,
            'annee_id'           => $annee->id,
            'email'              => 'koffi.ayivi@test.local',
            'identifiant_unique' => 'AYIVI_KOFFI_FEN-001_FEN_2025_2026',
        ]);

        $this->etudiant->ecs()->syncWithoutDetaching([
            $this->ec->id => ['annee_id' => $annee->id],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Fige l'horloge et renvoie un token QR valide à cet instant.
     */
    private function scannerA(string $heure): \Illuminate\Testing\TestResponse
    {
        Carbon::setTestNow(today()->setTimeFromTimeString($heure));

        $token = (string) Str::uuid();
        QrCode::where('evenement_id', $this->evenement->id)->update(['actif' => false]);
        QrCode::create([
            'evenement_id' => $this->evenement->id,
            'token'        => $token,
            'expire_at'    => Carbon::now()->addMinutes(5),
            'actif'        => true,
        ]);

        $device = 'device-fenetre';

        return $this->withToken($this->jetonDeScan($this->etudiant))->postJson('/api/presence/scan', [
            'token'              => $token,
            'device_fingerprint' => $device,
        ]);
    }

    public function test_refuse_une_minute_avant_l_ouverture(): void
    {
        $this->scannerA('09:44:00')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_accepte_exactement_a_l_ouverture(): void
    {
        $this->scannerA('09:45:00')->assertStatus(201);
    }

    public function test_accepte_exactement_a_la_fermeture(): void
    {
        $this->scannerA('10:10:00')->assertStatus(201);
    }

    public function test_refuse_une_minute_apres_la_fermeture(): void
    {
        $this->scannerA('10:11:00')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    /**
     * Régression : la fenêtre s'ouvrait à l'heure de début, ce qui permettait de
     * valider sa présence en début de séance puis de quitter la salle.
     */
    public function test_refuse_en_milieu_de_seance(): void
    {
        $this->scannerA('08:30:00')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_cours_a_cheval_sur_minuit(): void
    {
        $this->evenement->update([
            'heure_debut' => '23:00:00',
            'heure_fin'   => '00:30:00',
        ]);

        // La fenêtre est 00:15 → 00:40 le LENDEMAIN de la date de l'événement.
        Carbon::setTestNow(today()->addDay()->setTimeFromTimeString('00:20:00'));

        $token = (string) Str::uuid();
        QrCode::create([
            'evenement_id' => $this->evenement->id,
            'token'        => $token,
            'expire_at'    => Carbon::now()->addMinutes(5),
            'actif'        => true,
        ]);

        $device = 'device-minuit';

        $this->withToken($this->jetonDeScan($this->etudiant))->postJson('/api/presence/scan', [
            'token'              => $token,
            'device_fingerprint' => $device,
        ])->assertStatus(201);
    }

    public function test_expiration_du_token_plafonnee_a_la_fermeture(): void
    {
        // À 10:09:30, un TTL de 60 s dépasserait la fermeture (10:10).
        $maintenant = today()->setTimeFromTimeString('10:09:30');

        $this->assertSame(
            '10:10:00',
            $this->evenement->expirationTokenDepuis($maintenant)->format('H:i:s'),
            "L'expiration doit être ramenée à la fermeture de la fenêtre."
        );

        // Loin de la fermeture, le TTL nominal s'applique.
        $this->assertSame(
            '09:51:00',
            $this->evenement->expirationTokenDepuis(today()->setTimeFromTimeString('09:50:00'))->format('H:i:s'),
            'Hors plafonnement, le TTL nominal doit être respecté.'
        );
    }

    /**
     * Régression : la commande ne sélectionnait que les événements « planifie »
     * et les basculait en « en_cours » après la première génération. L'événement
     * quittait donc la sélection et son token n'était jamais renouvelé — le QR
     * « dynamique » restait le même pendant toute la séance.
     */
    public function test_le_qr_code_tourne_a_chaque_passage(): void
    {
        Carbon::setTestNow(today()->setTimeFromTimeString('09:50:00'));

        $this->artisan('qrcode:auto-generate')->assertSuccessful();

        $premier = QrCode::where('evenement_id', $this->evenement->id)->where('actif', true)->first();
        $this->assertNotNull($premier, 'Un premier token doit être généré dans la fenêtre.');
        $this->assertSame('en_cours', $this->evenement->fresh()->statut);

        Carbon::setTestNow(today()->setTimeFromTimeString('09:51:00'));

        $this->artisan('qrcode:auto-generate')->assertSuccessful();

        $second = QrCode::where('evenement_id', $this->evenement->id)->where('actif', true)->first();

        $this->assertNotNull($second, "L'événement déjà « en_cours » doit rester éligible à la rotation.");
        $this->assertNotSame($premier->token, $second->token, 'Le token doit changer à chaque passage.');
        $this->assertFalse((bool) $premier->fresh()->actif, "L'ancien token doit être désactivé.");
    }

    public function test_aucune_generation_hors_fenetre(): void
    {
        Carbon::setTestNow(today()->setTimeFromTimeString('08:30:00'));

        $this->artisan('qrcode:auto-generate')->assertSuccessful();

        $this->assertSame(
            0,
            QrCode::where('evenement_id', $this->evenement->id)->count(),
            'Aucun token ne doit exister avant l\'ouverture de la fenêtre.'
        );
        $this->assertSame('planifie', $this->evenement->fresh()->statut);
    }
}
