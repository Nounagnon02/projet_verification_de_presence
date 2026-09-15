<?php

namespace Tests\Feature\Presence;

use App\Models\AuditLog;
use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\Ue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Saisie par l'administration d'un étudiant qui n'a pas pu scanner.
 *
 * Horloge figée à midi : la séance de 08h à 10h est terminée.
 */
class SaisieManuelleTest extends TestCase
{
    private Etablissement $etab;
    private Filiere $filiere;
    private Ec $ec;
    private Evenement $seance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-03-10 12:00:00'));

        $sfx = Str::random(5);
        $this->etab = Etablissement::create(['code' => 'SM' . $sfx, 'nom' => 'Étab ' . $sfx, 'email' => strtolower("sm.{$sfx}@test.local")]);
        $this->filiere = Filiere::create(['code' => 'FSM' . $sfx, 'intitule' => 'Filière', 'niveau' => 'L1', 'etablissement_id' => $this->etab->id]);
        $ue = Ue::create([
            'code' => 'UESM' . $sfx, 'intitule' => 'UE', 'filiere_id' => $this->filiere->id,
            'annee_id' => $this->anneeActive()->id, 'semestre' => 1, 'volume_horaire' => 30,
        ]);
        $this->ec = Ec::create(['ue_id' => $ue->id, 'code' => 'ECSM' . $sfx, 'intitule' => 'Cours saisi', 'volume_horaire' => 30]);
        $this->seance = $this->seanceLe('2026-03-10');
    }

    public function test_la_liste_des_etudiants_attendus_suit_la_regle_du_scan(): void
    {
        $inscrit = $this->etudiant('Inscrit', inscrit: true);
        // Aucune inscription, même filière : attendu, comme au scan.
        $sansInscription = $this->etudiant('SansInscription', inscrit: false);
        // Autre filière, aucune inscription : pas attendu.
        $autreFiliere = Filiere::create(['code' => 'FSX' . Str::random(5), 'intitule' => 'Autre', 'niveau' => 'L2']);
        $etranger = $this->etudiant('Etranger', inscrit: false, filiere: $autreFiliere);
        $this->presence($inscrit, 'suspect');

        $etudiants = collect($this->enTantQue($this->superAdmin())
            ->getJson("/api/admin/presence/manuelle/{$this->seance->id}/etudiants")
            ->assertOk()
            ->json('data.etudiants'));

        $this->assertEqualsCanonicalizing([$inscrit->id, $sansInscription->id], $etudiants->pluck('id')->all());
        $this->assertNotContains($etranger->id, $etudiants->pluck('id')->all());
        $this->assertSame('suspect', $etudiants->firstWhere('id', $inscrit->id)['presence']['statut']);
        $this->assertNull($etudiants->firstWhere('id', $sansInscription->id)['presence']);
    }

    public function test_marquer_present_un_etudiant_qui_n_a_pas_pu_scanner(): void
    {
        $etudiant = $this->etudiant('Absent', inscrit: true);
        $admin = $this->superAdmin();

        $this->enTantQue($admin)->postJson('/api/admin/presence/manuelle', [
            'evenement_id' => $this->seance->id, 'etudiant_id' => $etudiant->id, 'motif' => 'Téléphone déchargé, présent en salle',
        ])->assertCreated();

        $presence = Presence::where('etudiant_id', $etudiant->id)->where('evenement_id', $this->seance->id)->firstOrFail();
        $this->assertSame('valide', $presence->statut);
        $this->assertEquals($admin->id, $presence->validated_by);
        $this->assertSame('Téléphone déchargé, présent en salle', $presence->validation_motif);
        // Séance terminée : la présence est datée de sa fin, pas de la saisie.
        $this->assertSame('2026-03-10 10:00:00', $presence->heure_scan->format('Y-m-d H:i:s'));
        $this->assertTrue(AuditLog::where('action', 'presence.saisie_manuelle')
            ->where('model_id', $presence->id)->where('user_id', $admin->id)->exists());
    }

    public function test_pendant_la_seance_l_heure_retenue_est_celle_de_la_saisie(): void
    {
        $this->travelTo(Carbon::parse('2026-03-10 09:20:00'));
        $etudiant = $this->etudiant('EnCours', inscrit: true);

        $this->enTantQue($this->superAdmin())->postJson('/api/admin/presence/manuelle', [
            'evenement_id' => $this->seance->id, 'etudiant_id' => $etudiant->id, 'motif' => 'Sans téléphone',
        ])->assertCreated();

        $this->assertSame('09:20', Presence::where('etudiant_id', $etudiant->id)->firstOrFail()->heure_scan->format('H:i'));
    }

    public function test_les_garde_fous(): void
    {
        $admin = $this->superAdmin();
        $etudiant = $this->etudiant('Garde', inscrit: true);
        $saisir = fn (Evenement $seance, Etudiant $e, string $motif = 'Présent') => $this->enTantQue($admin)
            ->postJson('/api/admin/presence/manuelle', ['evenement_id' => $seance->id, 'etudiant_id' => $e->id, 'motif' => $motif]);

        // Motif absent.
        $saisir($this->seance, $etudiant, '')->assertStatus(422);
        // Séance pas encore commencée.
        $saisir($this->seanceLe('2026-03-11'), $etudiant)->assertStatus(422)
            ->assertJsonPath('message', "Cette séance n'a pas encore commencé.");
        // Séance annulée.
        $annulee = $this->seanceLe('2026-03-09');
        $annulee->update(['statut' => 'annule']);
        $saisir($annulee, $etudiant)->assertStatus(422);
        // Étudiant non inscrit au cours.
        $autreFiliere = Filiere::create(['code' => 'FSY' . Str::random(5), 'intitule' => 'Autre', 'niveau' => 'L2', 'etablissement_id' => $this->etab->id]);
        $saisir($this->seance, $this->etudiant('Ailleurs', inscrit: false, filiere: $autreFiliere))->assertStatus(422);
        $this->assertSame(0, Presence::where('evenement_id', $this->seance->id)->count());

        // Présence suspecte déjà là : elle se tranche dans la file.
        $this->presence($etudiant, 'suspect');
        $saisir($this->seance, $etudiant)->assertStatus(409)
            ->assertJsonPath('message', "Une présence suspecte existe déjà pour cette séance : tranchez-la dans la file d'attente.");
        $this->assertSame('suspect', Presence::where('etudiant_id', $etudiant->id)->firstOrFail()->statut);
    }

    public function test_un_admin_d_une_autre_faculte_ne_voit_ni_ne_saisit(): void
    {
        $etudiant = $this->etudiant('Protege', inscrit: true);
        $autre = Etablissement::create(['code' => 'SMX' . Str::random(4), 'nom' => 'Autre', 'email' => strtolower('smx' . Str::random(5) . '@test.local')]);
        $adminAutre = User::factory()->create([
            'email' => 'sm-' . Str::random(6) . '@example.test', 'role' => 'faculte_admin', 'etablissement_id' => $autre->id,
        ]);

        $this->enTantQue($adminAutre)->getJson("/api/admin/presence/manuelle/{$this->seance->id}/etudiants")->assertNotFound();
        $this->enTantQue($adminAutre)->postJson('/api/admin/presence/manuelle', [
            'evenement_id' => $this->seance->id, 'etudiant_id' => $etudiant->id, 'motif' => 'Présent',
        ])->assertNotFound();

        $this->assertSame(0, Presence::where('etudiant_id', $etudiant->id)->count());
    }

    private function seanceLe(string $date): Evenement
    {
        return Evenement::create([
            'ec_id' => $this->ec->id, 'filiere_id' => $this->filiere->id, 'annee_id' => $this->ec->ue->annee_id,
            'date' => $date, 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'statut' => 'planifie',
        ]);
    }

    private function etudiant(string $nom, bool $inscrit, ?Filiere $filiere = null): Etudiant
    {
        $sfx = Str::random(6);
        $etudiant = Etudiant::create([
            'nom' => $nom, 'prenom' => 'Test', 'matricule' => 'SM-' . $sfx,
            'filiere_id' => ($filiere ?? $this->filiere)->id, 'annee_id' => $this->anneeActive()->id,
            'email' => strtolower("sm-{$sfx}@example.test"), 'identifiant_unique' => 'SM_' . $sfx,
        ]);

        if ($inscrit) {
            $etudiant->ecs()->attach([$this->ec->id => ['annee_id' => $this->anneeActive()->id]]);
        }

        return $etudiant;
    }

    private function presence(Etudiant $etudiant, string $statut): Presence
    {
        return Presence::create([
            'etudiant_id' => $etudiant->id, 'evenement_id' => $this->seance->id,
            'heure_scan' => '2026-03-10 09:50:00', 'device_fingerprint' => 'tel-sm', 'statut' => $statut,
        ]);
    }

    private function enTantQue(User $utilisateur): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $utilisateur->createToken('t')->plainTextToken);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['email' => 'sm-' . Str::random(6) . '@example.test', 'role' => 'super_admin']);
    }
}
