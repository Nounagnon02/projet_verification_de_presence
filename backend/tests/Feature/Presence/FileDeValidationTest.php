<?php

namespace Tests\Feature\Presence;

use App\Models\Anomaly;
use App\Models\AuditLog;
use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * File d'arbitrage des scans suspects (GET /api/admin/presence/pending).
 *
 * Les assertions filtrent sur les séances créées ici : la base de test peut
 * contenir d'autres présences suspectes.
 */
class FileDeValidationTest extends TestCase
{
    private Etablissement $etab;
    private Filiere $filiere;
    private Evenement $seance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->etab = $this->etablissement();
        $this->filiere = $this->filiereDe($this->etab);
        $this->seance = $this->seanceDe($this->filiere);
    }

    public function test_chaque_scan_suspect_montre_les_autres_etudiants_du_meme_telephone(): void
    {
        $a = $this->presence($this->etudiant('Avognon'), 'tel-1', '09:45');
        $b = $this->presence($this->etudiant('Bossou'), 'tel-1', '09:46');
        $c = $this->presence($this->etudiant('Codjo'), 'tel-1', '09:47', 'rejete');
        $d = $this->presence($this->etudiant('Dossou'), 'tel-2', '09:48');

        $lignes = $this->file($this->superAdmin(), ['evenement_id' => $this->seance->id])
            ->assertOk()
            ->json('data.data');

        // Seuls les suspects sont à arbitrer, et le groupe d'un téléphone se suit.
        $this->assertSame([$a->id, $b->id, $d->id], array_column($lignes, 'id'));

        $ligneB = collect($lignes)->firstWhere('id', $b->id);
        $voisins = collect($ligneB['meme_appareil']);
        $this->assertEqualsCanonicalizing([$a->id, $c->id], $voisins->pluck('id')->all());
        $this->assertSame('Codjo', $voisins->firstWhere('id', $c->id)['etudiant']['nom']);
        // Un voisin déjà rejeté reste visible : c'est une information pour trancher.
        $this->assertSame('rejete', $voisins->firstWhere('id', $c->id)['statut']);

        $this->assertSame([], collect($lignes)->firstWhere('id', $d->id)['meme_appareil']);
    }

    public function test_la_recherche_porte_sur_toute_la_file_et_non_sur_une_page(): void
    {
        $this->presence($this->etudiant('Avognon'), 'tel-1', '09:45');
        $cible = $this->etudiant('Bossou');
        $this->presence($cible, 'tel-1', '09:46');
        $this->presence($this->etudiant('Codjo'), 'tel-2', '09:47');

        $admin = $this->superAdmin();
        $filtre = ['evenement_id' => $this->seance->id, 'per_page' => 1];

        $parMatricule = $this->file($admin, $filtre + ['search' => $cible->matricule])->assertOk();
        $this->assertSame(1, $parMatricule->json('data.total'));
        $this->assertSame($cible->id, $parMatricule->json('data.data.0.etudiant.id'));

        // Prénom puis nom, comme on l'écrit à l'écran, sans tenir compte de la casse.
        $parNomComplet = $this->file($admin, $filtre + ['search' => 'test bossou'])->assertOk();
        $this->assertSame(1, $parNomComplet->json('data.total'));
    }

    public function test_un_admin_de_faculte_ne_voit_que_les_scans_de_son_etablissement(): void
    {
        $this->presence($this->etudiant('Avognon'), 'tel-1', '09:45');

        $autreFiliere = $this->filiereDe($this->etablissement());
        $autreSeance = $this->seanceDe($autreFiliere);
        $this->presence($this->etudiant('Etranger', $autreFiliere), 'tel-9', '09:45', 'suspect', $autreSeance);

        $adminFaculte = User::factory()->create([
            'email'            => 'fv-' . Str::random(6) . '@example.test',
            'role'             => 'faculte_admin',
            'etablissement_id' => $this->etab->id,
        ]);

        $this->assertSame(1, $this->file($adminFaculte, ['evenement_id' => $this->seance->id])->json('data.total'));
        $this->assertSame(0, $this->file($adminFaculte, ['evenement_id' => $autreSeance->id])->json('data.total'));
        $this->assertSame(1, $this->file($this->superAdmin(), ['evenement_id' => $autreSeance->id])->json('data.total'));
    }

    public function test_le_journal_garde_le_statut_de_depart_de_chaque_arbitrage(): void
    {
        $aValider = $this->presence($this->etudiant('Avognon'), 'tel-1', '09:45');
        $aRejeter = $this->presence($this->etudiant('Bossou'), 'tel-1', '09:46');
        $token = $this->superAdmin()->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson("/api/admin/presence/{$aValider->id}/validate", ['action' => 'valider'])
            ->assertOk();
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson("/api/admin/presence/{$aRejeter->id}/validate", ['action' => 'rejeter', 'motif' => 'Téléphone prêté'])
            ->assertOk();

        $validation = $this->journal($aValider, 'presence.validate_manual');
        $this->assertSame(['statut' => 'suspect'], $validation->old_values);
        $this->assertSame('valide', $validation->new_values['statut']);

        $rejet = $this->journal($aRejeter, 'presence.reject_manual');
        $this->assertSame(['statut' => 'suspect'], $rejet->old_values);
        $this->assertSame(['statut' => 'rejete', 'motif' => 'Téléphone prêté'], $rejet->new_values);
    }

    public function test_arbitrer_un_scan_ferme_son_alerte_d_appareil_partage(): void
    {
        $etudiant = $this->etudiant('Bossou');
        $presence = $this->presence($etudiant, 'tel-1', '09:46');
        $alerte = $this->alertePartage($etudiant, $this->seance);
        // Même étudiant, autre séance : cette alerte-là n'est pas tranchée.
        $autre = $this->alertePartage($etudiant, $this->seanceDe($this->filiere));

        $this->withHeader('Authorization', 'Bearer ' . $this->superAdmin()->createToken('t')->plainTextToken)
            ->patchJson("/api/admin/presence/{$presence->id}/validate", ['action' => 'valider'])
            ->assertOk();

        $this->assertTrue($alerte->fresh()->resolved);
        $this->assertFalse($autre->fresh()->resolved);
    }

    private function alertePartage(Etudiant $etudiant, Evenement $seance): Anomaly
    {
        return Anomaly::create([
            'etudiant_id' => $etudiant->id, 'type' => 'appareil_partage', 'description' => 'Appareil partagé',
            'severity' => 'high', 'metadata' => ['evenement_id' => $seance->id],
        ]);
    }

    private function journal(Presence $presence, string $action): AuditLog
    {
        return AuditLog::where('model_type', Presence::class)
            ->where('model_id', $presence->id)
            ->where('action', $action)
            ->latest('id')
            ->firstOrFail();
    }

    private function file(User $utilisateur, array $parametres = [])
    {
        // Le garde Sanctum garde l'utilisateur de la première requête du test :
        // sans cet oubli, un second utilisateur serait servi comme le premier.
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $utilisateur->createToken('t')->plainTextToken)
            ->getJson('/api/admin/presence/pending?' . http_build_query($parametres + ['per_page' => 100]));
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'email' => 'fv-' . Str::random(6) . '@example.test',
            'role'  => 'super_admin',
        ]);
    }

    private function etablissement(): Etablissement
    {
        $sfx = Str::random(5);

        return Etablissement::create([
            'code' => 'FV' . $sfx, 'nom' => 'Établissement ' . $sfx, 'email' => strtolower("fv.{$sfx}@test.local"),
        ]);
    }

    private function filiereDe(Etablissement $etablissement): Filiere
    {
        return Filiere::create([
            'code' => 'FFV' . Str::random(5), 'intitule' => 'Filière', 'niveau' => 'L1',
            'etablissement_id' => $etablissement->id,
        ]);
    }

    private function seanceDe(Filiere $filiere): Evenement
    {
        $ue = Ue::create([
            'code' => 'UEFV' . Str::random(5), 'intitule' => 'UE', 'filiere_id' => $filiere->id,
            'annee_id' => $this->anneeActive()->id, 'semestre' => 1, 'volume_horaire' => 30,
        ]);
        $ec = Ec::create(['ue_id' => $ue->id, 'code' => 'ECFV' . Str::random(5), 'intitule' => 'Cours file', 'volume_horaire' => 30]);

        return Evenement::create([
            'ec_id' => $ec->id, 'filiere_id' => $filiere->id, 'annee_id' => $ue->annee_id,
            'date' => today()->toDateString(), 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'statut' => 'termine',
        ]);
    }

    private function etudiant(string $nom, ?Filiere $filiere = null): Etudiant
    {
        $sfx = Str::random(6);

        return Etudiant::create([
            'nom' => $nom, 'prenom' => 'Test', 'matricule' => 'FV-' . $sfx,
            'filiere_id' => ($filiere ?? $this->filiere)->id, 'annee_id' => $this->anneeActive()->id,
            'email' => strtolower("fv-{$sfx}@example.test"), 'identifiant_unique' => 'FV_' . $sfx,
        ]);
    }

    private function presence(Etudiant $etudiant, string $telephone, string $heure, string $statut = 'suspect', ?Evenement $seance = null): Presence
    {
        $seance ??= $this->seance;

        return Presence::create([
            'etudiant_id'        => $etudiant->id,
            'evenement_id'       => $seance->id,
            'heure_scan'         => $seance->date->format('Y-m-d') . ' ' . $heure,
            'device_fingerprint' => $telephone,
            'ip_address'         => '127.0.0.1',
            'statut'             => $statut,
        ]);
    }
}
