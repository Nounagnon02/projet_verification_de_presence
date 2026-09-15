<?php

namespace Tests\Feature\Admin;

use App\Models\Anomaly;
use App\Models\AuditLog;
use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\QrCode;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Scans refusés (GET /api/admin/alerts), en lecture seule.
 *
 * Les refus sont produits par de vrais scans : c'est le chemin réel qui doit
 * noter la séance visée et rester hors de la file de validation.
 */
class ScansRefusesTest extends TestCase
{
    private Ec $ec;
    private Evenement $seance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);

        // Horloge figée dans la fenêtre de scan de la séance.
        Carbon::setTestNow(today()->setTime(10, 0));

        $sfx = Str::random(5);
        $annee = $this->anneeActive();
        $filiere = Filiere::create(['code' => 'SR' . $sfx, 'intitule' => 'Refus', 'niveau' => 'L1']);
        $ue = Ue::create([
            'code' => 'UESR' . $sfx, 'intitule' => 'UE', 'filiere_id' => $filiere->id,
            'annee_id' => $annee->id, 'semestre' => 1, 'volume_horaire' => 30,
        ]);
        $this->ec = Ec::create(['ue_id' => $ue->id, 'code' => 'ECSR' . $sfx, 'intitule' => 'Cours refusé', 'volume_horaire' => 30]);

        $this->seance = Evenement::create([
            'ec_id' => $this->ec->id, 'filiere_id' => $filiere->id, 'annee_id' => $annee->id,
            'date' => today()->format('Y-m-d'),
            'heure_debut' => Carbon::now()->subHour()->format('H:i:s'),
            'heure_fin' => Carbon::now()->addMinutes(5)->format('H:i:s'),
            'statut' => 'en_cours',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_un_scan_refuse_apparait_avec_la_seance_visee(): void
    {
        $etudiant = $this->etudiant();
        $this->scan($etudiant, 'tel-1')->assertStatus(201);
        // Second scan depuis un autre téléphone : refusé.
        $this->scan($etudiant, 'tel-2')->assertStatus(409);

        $ligne = collect($this->refus(['search' => $etudiant->matricule])->assertOk()->json('data.data'))
            ->firstWhere('etudiant.id', $etudiant->id);

        $this->assertNotNull($ligne);
        $this->assertSame('double_scan_device_mismatch', $ligne['type']);
        $this->assertSame($this->seance->id, $ligne['evenement']['id']);
        $this->assertSame('Cours refusé', $ligne['evenement']['cours']);
    }

    public function test_les_scans_suspects_n_y_figurent_pas(): void
    {
        $a = $this->etudiant();
        $b = $this->etudiant();
        $this->scan($a, 'tel-partage')->assertStatus(201);
        $this->scan($b, 'tel-partage')->assertStatus(201);

        // L'alerte existe, mais ce scan se tranche dans la file, pas ici.
        $this->assertDatabaseHas('anomalies', ['type' => 'appareil_partage', 'etudiant_id' => $b->id]);
        $this->assertSame(0, $this->refus(['search' => $b->matricule])->json('data.total'));
    }

    public function test_plus_aucune_decision_ne_se_prend_depuis_cette_page(): void
    {
        // « Valide » sur un double scan repassait la première présence en
        // « valide », sans motif ni trace, même rejetée dans la file.
        $etudiant = $this->etudiant();
        $this->scan($etudiant, 'tel-1')->assertStatus(201);
        $presence = Presence::where('etudiant_id', $etudiant->id)->firstOrFail();
        $presence->update(['statut' => 'rejete']);
        $this->scan($etudiant, 'tel-2')->assertStatus(409);

        $alerte = Anomaly::where('etudiant_id', $etudiant->id)->where('type', 'double_scan_device_mismatch')->firstOrFail();
        $reponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdmin()->createToken('t')->plainTextToken)
            ->postJson("/api/admin/alerts/{$alerte->id}/resolve", ['status' => 'valide']);

        $this->assertContains($reponse->status(), [404, 405]);
        $this->assertSame('rejete', $presence->fresh()->statut);
    }

    public function test_enregistrer_la_presence_d_un_etudiant_refuse_a_tort(): void
    {
        $etudiant = $this->etudiant();
        // Refus réel : défi de sécurité invalide ; la séance est notée.
        $this->scan($etudiant, 'tel-1', 'defi-faux')->assertStatus(403);
        $refus = Anomaly::where('etudiant_id', $etudiant->id)->where('type', 'invalid_scan_challenge')->firstOrFail();
        $admin = $this->superAdmin();

        $this->enTantQue($admin)
            ->postJson("/api/admin/alerts/{$refus->id}/presence", ['motif' => "Présent, confirmé par l'enseignant"])
            ->assertCreated();

        $presence = Presence::where('etudiant_id', $etudiant->id)->where('evenement_id', $this->seance->id)->firstOrFail();
        $this->assertSame('valide', $presence->statut);
        $this->assertEquals($admin->id, $presence->validated_by);
        $this->assertSame("Présent, confirmé par l'enseignant", $presence->validation_motif);
        // L'heure retenue est celle de la tentative, pas celle de l'enregistrement.
        $this->assertSame($refus->created_at->toDateTimeString(), $presence->heure_scan->toDateTimeString());

        $this->assertTrue(AuditLog::where('action', 'presence.enregistrement_manuel')
            ->where('model_id', $presence->id)->where('user_id', $admin->id)->exists());

        $ligne = collect($this->refus(['search' => $etudiant->matricule])->json('data.data'))->firstWhere('id', $refus->id);
        $this->assertSame('valide', $ligne['presence']['statut']);
        $this->assertTrue($ligne['presence']['depuis_ce_refus']);
    }

    public function test_le_motif_est_obligatoire(): void
    {
        $etudiant = $this->etudiant();
        $this->scan($etudiant, 'tel-1', 'defi-faux')->assertStatus(403);
        $refus = Anomaly::where('etudiant_id', $etudiant->id)->firstOrFail();

        $this->enTantQue($this->superAdmin())
            ->postJson("/api/admin/alerts/{$refus->id}/presence", ['motif' => ''])
            ->assertStatus(422);

        $this->assertSame(0, Presence::where('etudiant_id', $etudiant->id)->count());
    }

    public function test_aucune_presence_sans_seance_connue_ni_pour_un_etudiant_non_inscrit(): void
    {
        $etudiant = $this->etudiant();
        $sansSeance = Anomaly::create([
            'etudiant_id' => $etudiant->id, 'type' => 'verification_echouee',
            'description' => 'Refus ancien, séance non notée', 'severity' => 'medium',
        ]);
        $this->enTantQue($this->superAdmin())
            ->postJson("/api/admin/alerts/{$sansSeance->id}/presence", ['motif' => 'Présent'])
            ->assertStatus(422);

        // Étudiant d'une autre filière et sans inscription : pas à ce cours.
        $sfx = Str::random(6);
        $horsCours = Etudiant::create([
            'nom' => 'SRX', 'prenom' => $sfx, 'matricule' => 'SRX-' . $sfx,
            'filiere_id' => Filiere::create(['code' => 'SRX' . $sfx, 'intitule' => 'Autre', 'niveau' => 'L2'])->id,
            'annee_id' => $this->seance->annee_id,
            'email' => strtolower("srx-{$sfx}@example.test"), 'identifiant_unique' => 'SRX_' . $sfx,
        ]);
        $refus = Anomaly::create([
            'etudiant_id' => $horsCours->id, 'type' => 'verification_echouee', 'description' => 'Hors zone',
            'severity' => 'medium', 'metadata' => ['evenement_id' => $this->seance->id],
        ]);
        $this->enTantQue($this->superAdmin())
            ->postJson("/api/admin/alerts/{$refus->id}/presence", ['motif' => 'Présent'])
            ->assertStatus(422);

        $this->assertSame(0, Presence::where('evenement_id', $this->seance->id)->count());
    }

    public function test_pas_de_seconde_presence_ni_d_acces_hors_de_son_etablissement(): void
    {
        $etudiant = $this->etudiant();
        $this->scan($etudiant, 'tel-1')->assertStatus(201);
        $this->scan($etudiant, 'tel-2')->assertStatus(409);
        $doubleScan = Anomaly::where('etudiant_id', $etudiant->id)->where('type', 'double_scan_device_mismatch')->firstOrFail();

        // Déjà présent : rien à enregistrer.
        $this->enTantQue($this->superAdmin())
            ->postJson("/api/admin/alerts/{$doubleScan->id}/presence", ['motif' => 'Présent'])
            ->assertStatus(409);

        // Un admin d'une autre faculté ne voit pas ce refus.
        $sfx = Str::random(5);
        $autreEtab = Etablissement::create(['code' => 'SRE' . $sfx, 'nom' => 'Autre ' . $sfx, 'email' => strtolower("sre.{$sfx}@test.local")]);
        $adminAutre = User::factory()->create([
            'email' => 'sr-' . Str::random(6) . '@example.test', 'role' => 'faculte_admin', 'etablissement_id' => $autreEtab->id,
        ]);
        $autreEtudiant = $this->etudiant();
        $refus = Anomaly::create([
            'etudiant_id' => $autreEtudiant->id, 'type' => 'verification_echouee', 'description' => 'Hors zone',
            'severity' => 'medium', 'metadata' => ['evenement_id' => $this->seance->id],
        ]);
        $this->enTantQue($adminAutre)
            ->postJson("/api/admin/alerts/{$refus->id}/presence", ['motif' => 'Présent'])
            ->assertNotFound();

        $this->assertSame(0, Presence::where('etudiant_id', $autreEtudiant->id)->count());
    }

    private function enTantQue(User $utilisateur): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $utilisateur->createToken('t')->plainTextToken);
    }

    private function etudiant(): Etudiant
    {
        $sfx = Str::random(6);
        $etudiant = Etudiant::create([
            'nom' => 'SR', 'prenom' => $sfx, 'matricule' => 'SR-' . $sfx,
            'filiere_id' => $this->seance->filiere_id, 'annee_id' => $this->seance->annee_id,
            'email' => strtolower("sr-{$sfx}@example.test"), 'identifiant_unique' => 'SR_' . $sfx,
        ]);
        $etudiant->ecs()->syncWithoutDetaching([$this->ec->id => ['annee_id' => $this->seance->annee_id]]);

        return $etudiant;
    }

    private function scan(Etudiant $etudiant, string $telephone, ?string $defi = null): \Illuminate\Testing\TestResponse
    {
        // Un jeton neuf par scan, comme la rotation du planificateur.
        QrCode::where('evenement_id', $this->seance->id)->update(['actif' => false]);
        $token = (string) Str::uuid();
        QrCode::create(['evenement_id' => $this->seance->id, 'token' => $token, 'expire_at' => Carbon::now()->addMinutes(5), 'actif' => true]);

        return $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $etudiant->identifiant_unique,
            'token'              => $token,
            'device_fingerprint' => $telephone,
            'scan_challenge'     => $defi ?? $this->defiDeScan($token),
        ]);
    }

    private function refus(array $parametres = [])
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $this->superAdmin()->createToken('t')->plainTextToken)
            ->getJson('/api/admin/alerts?' . http_build_query($parametres + ['per_page' => 100]));
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['email' => 'sr-' . Str::random(6) . '@example.test', 'role' => 'super_admin']);
    }
}
