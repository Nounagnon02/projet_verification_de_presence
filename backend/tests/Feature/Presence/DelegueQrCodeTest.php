<?php

namespace Tests\Feature\Presence;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\QrCode;
use App\Models\Ue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Consultation du QR Code par l'étudiant responsable (délégué).
 *
 * Couvre l'accès du délégué dans et hors fenêtre, le refus d'un étudiant
 * ordinaire, l'isolation entre promotions, l'absence de tout chemin de
 * génération, et le cloisonnement des jetons entre étudiants et administrateurs.
 */
class DelegueQrCodeTest extends TestCase
{
    private Etudiant $delegue;
    private Etudiant $simple;
    private Evenement $evenement;
    private Ec $ec;
    private AnneeAcademique $annee;
    private Filiere $filiere;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        Config::set('presence.scan.minutes_avant_fin', 15);
        Config::set('presence.scan.minutes_apres_fin', 10);
        Config::set('presence.qr.ttl_secondes', 60);
        Config::set('presence.qr.visible_delegue_avant_fin', 10);

        $sfx = Str::random(5);

        $this->filiere = Filiere::create([
            'code'     => 'DEL-' . $sfx,
            'intitule' => 'Filière Délégué',
            'niveau'   => 'L3',
        ]);

        $this->annee = AnneeAcademique::create([
            'libelle'    => '2096-2097 ' . $sfx,
            'date_debut' => '2096-10-01',
            'date_fin'   => '2097-09-30',
            'active'     => false,
        ]);

        $ue = Ue::create([
            'code'           => 'UE-' . $sfx,
            'intitule'       => 'UE Délégué',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 30,
        ]);

        $this->ec = Ec::create([
            'ue_id'          => $ue->id,
            'code'           => 'EC-' . $sfx,
            'intitule'       => 'EC Délégué',
            'volume_horaire' => 30,
        ]);

        // Cours de 08:00 à 10:00 : QR visible par le délégué dès 09:50,
        // fenêtre de scan close à 10:10.
        $this->evenement = Evenement::create([
            'ec_id'       => $this->ec->id,
            'filiere_id'  => $this->filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => today()->format('Y-m-d'),
            'heure_debut' => '08:00:00',
            'heure_fin'   => '10:00:00',
            'salle'       => 'Amphi Délégué',
            'statut'      => 'en_cours',
        ]);

        $this->delegue = $this->creerEtudiant('DELEGUE', true);
        $this->simple  = $this->creerEtudiant('SIMPLE', false);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function creerEtudiant(string $nom, bool $responsable, ?Ec $ec = null): Etudiant
    {
        $sfx = Str::random(6);

        $etudiant = Etudiant::create([
            'id'                 => (string) Str::uuid(),
            'nom'                => $nom,
            'prenom'             => 'TEST',
            'matricule'          => $nom . '-' . $sfx,
            'filiere_id'         => $this->filiere->id,
            'annee_id'           => $this->annee->id,
            'email'              => strtolower("{$nom}.{$sfx}@test.local"),
            'identifiant_unique' => "{$nom}_TEST_{$sfx}",
            'est_responsable'    => $responsable,
        ]);

        $etudiant->ecs()->syncWithoutDetaching([
            ($ec ?? $this->ec)->id => ['annee_id' => $this->annee->id],
        ]);

        return $etudiant;
    }

    private function tokenEtudiant(Etudiant $etudiant): string
    {
        return $etudiant->createToken('mobile-app', ['etudiant'])->plainTextToken;
    }

    private function consulter(Etudiant $etudiant): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->tokenEtudiant($etudiant))
            ->getJson('/api/student/qrcode/current');
    }

    private function creerQrActif(): QrCode
    {
        return QrCode::create([
            'evenement_id' => $this->evenement->id,
            'token'        => (string) Str::uuid(),
            'expire_at'    => Carbon::now()->addSeconds(60),
            'actif'        => true,
        ]);
    }

    public function test_authentification_requise(): void
    {
        $this->getJson('/api/student/qrcode/current')->assertStatus(401);
    }

    public function test_le_delegue_obtient_le_qr_dans_la_fenetre(): void
    {
        Carbon::setTestNow(today()->setTimeFromTimeString('09:52:00'));
        $qr = $this->creerQrActif();

        $reponse = $this->consulter($this->delegue)
            ->assertStatus(200)
            ->assertJsonPath('data.token', $qr->token)
            ->assertJsonPath('data.evenement.code', $this->ec->code)
            ->assertJsonPath('data.evenement.ferme_a', '10:10');

        // L'image est produite par le serveur : ni l'application mobile ni un
        // service tiers n'a à encoder le token.
        $svg = $reponse->json('data.svg');
        $this->assertIsString($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString($qr->token, $reponse->json('data.url'));
    }

    public function test_le_delegue_ne_voit_rien_avant_l_ouverture(): void
    {
        // 09:49 : une minute avant la visibilité fixée à 10 min de la fin.
        Carbon::setTestNow(today()->setTimeFromTimeString('09:49:00'));
        $this->creerQrActif();

        $this->consulter($this->delegue)->assertStatus(404);
    }

    public function test_le_delegue_ne_voit_rien_apres_la_fermeture(): void
    {
        Carbon::setTestNow(today()->setTimeFromTimeString('10:11:00'));
        $this->creerQrActif();

        $this->consulter($this->delegue)->assertStatus(404);
    }

    public function test_un_etudiant_ordinaire_est_refuse(): void
    {
        Carbon::setTestNow(today()->setTimeFromTimeString('09:52:00'));
        $this->creerQrActif();

        $this->consulter($this->simple)
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_aucun_qr_actif_renvoie_404_explicite(): void
    {
        Carbon::setTestNow(today()->setTimeFromTimeString('09:52:00'));
        // Aucun QR créé : le délégué ne doit surtout pas en déclencher un.

        $this->consulter($this->delegue)->assertStatus(404);

        $this->assertSame(
            0,
            QrCode::where('evenement_id', $this->evenement->id)->count(),
            'La consultation ne doit jamais générer de QR Code.'
        );
    }

    public function test_isolation_entre_promotions(): void
    {
        Carbon::setTestNow(today()->setTimeFromTimeString('09:52:00'));
        $this->creerQrActif();

        // Un délégué d'une autre filière, inscrit à un autre EC, ne doit pas
        // obtenir le code de cette séance.
        $autreFiliere = Filiere::create([
            'code'     => 'AUT-' . Str::random(5),
            'intitule' => 'Autre filière',
            'niveau'   => 'L3',
        ]);

        $autreUe = Ue::create([
            'code'           => 'UE-AUT-' . Str::random(5),
            'intitule'       => 'Autre UE',
            'filiere_id'     => $autreFiliere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 30,
        ]);

        $autreEc = Ec::create([
            'ue_id'          => $autreUe->id,
            'code'           => 'EC-AUT-' . Str::random(5),
            'intitule'       => 'Autre EC',
            'volume_horaire' => 30,
        ]);

        $intrus = $this->creerEtudiant('INTRUS', true, $autreEc);
        $intrus->update(['filiere_id' => $autreFiliere->id]);

        $this->consulter($intrus)->assertStatus(404);
    }

    /**
     * Prérequis de sécurité du lot : auth:sanctum authentifie indifféremment un
     * administrateur et un étudiant. Sans capacité, un jeton d'étudiant
     * atteignait le middleware de cloisonnement, qui appelle une méthode absente
     * du modèle Etudiant — d'où une erreur 500 sur une route d'administration.
     */
    public function test_un_jeton_etudiant_est_refuse_sur_une_route_admin(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenEtudiant($this->delegue))
            ->getJson('/api/admin/evenements')
            ->assertStatus(403);
    }

    public function test_un_jeton_admin_reste_accepte_sur_les_routes_admin(): void
    {
        // Les jetons d'administrateur sont créés sans capacité, donc porteurs de
        // « * » : l'ajout du contrôle ne doit invalider aucune session en cours.
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->withHeader('Authorization', 'Bearer ' . $admin->createToken('api-token')->plainTextToken)
            ->getJson('/api/admin/evenements')
            ->assertStatus(200);
    }

    public function test_un_jeton_admin_est_refuse_sur_une_route_etudiant(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        // « * » satisfait ability:etudiant ; c'est le contrôle de type dans le
        // contrôleur qui doit alors refuser un utilisateur non étudiant.
        $this->withHeader('Authorization', 'Bearer ' . $admin->createToken('api-token')->plainTextToken)
            ->getJson('/api/student/qrcode/current')
            ->assertStatus(403);
    }
}
