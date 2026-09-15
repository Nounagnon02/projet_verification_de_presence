<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Une année antérieure à l'année active de l'établissement est close : sa
 * structure ne se modifie plus, ses présences restent corrigeables. La règle
 * vaut par établissement — une faculté restée sur cette année y écrit encore.
 */
class AnneesClosesTest extends TestCase
{
    private string $sfx;
    private string $jeton;
    private AnneeAcademique $ancienne;
    private AnneeAcademique $active;
    private Filiere $filiere;
    private Ue $ue;
    private Ec $ec;
    private Evenement $seance;
    private Etudiant $etudiant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx = Str::lower(Str::random(6));
        $etab = Etablissement::create(['code' => 'AC' . $this->sfx, 'nom' => 'Faculté C', 'email' => "ac-{$this->sfx}@test.local"]);
        $this->jeton = User::factory()->faculteAdmin($etab->id)->create(['email' => "close-{$this->sfx}@test.local"])->createToken('t')->plainTextToken;

        AnneeAcademique::where('active', true)->update(['active' => false]);
        $this->ancienne = AnneeAcademique::create(['libelle' => '2051-2052', 'date_debut' => '2051-10-01', 'date_fin' => '2052-09-30']);
        $this->active = AnneeAcademique::create(['libelle' => '2052-2053', 'date_debut' => '2052-10-01', 'date_fin' => '2053-09-30', 'active' => true]);

        $this->filiere = Filiere::create(['code' => 'CL' . $this->sfx, 'intitule' => 'Close (L1)', 'niveau' => 'L1', 'etablissement_id' => $etab->id]);
        $this->ue = Ue::factory()->pour($this->filiere, $this->ancienne)->create(['semestre' => 1]);
        $this->ec = Ec::factory()->create(['ue_id' => $this->ue->id, 'volume_horaire' => 40]);
        $this->seance = Evenement::factory()->pourEc($this->ec)->passe()->create();

        $this->etudiant = Etudiant::factory()->pour($this->filiere, $this->ancienne)->create();
        $this->etudiant->autoEnroll();
    }

    private function api(string $methode, string $uri, array $donnees = [], ?string $jeton = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($jeton ?? $this->jeton)->json($methode, "/api/admin/{$uri}", $donnees);
    }

    private function assertClose(TestResponse $reponse): void
    {
        $reponse->assertStatus(409);
        $this->assertStringContainsString('2051-2052 est close pour votre établissement', $reponse->json('message'));
    }

    public function test_la_liste_dit_quelles_annees_sont_closes(): void
    {
        $annees = collect($this->api('GET', 'annees-academiques')->assertOk()->json('data'))->keyBy('id');

        $this->assertTrue($annees[$this->ancienne->id]['close']);
        $this->assertFalse($annees[$this->active->id]['close']);
    }

    public function test_la_maquette_d_une_annee_close_ne_bouge_plus(): void
    {
        $this->assertClose($this->api('POST', 'ues', ['code' => 'NV', 'intitule' => 'Nouvelle', 'filiere_id' => $this->filiere->id, 'annee_id' => $this->ancienne->id, 'semestre' => 1, 'volume_horaire' => 20]));
        $this->assertClose($this->api('PUT', "ues/{$this->ue->id}", ['intitule' => 'Renommée']));
        $this->assertClose($this->api('DELETE', "ues/{$this->ue->id}"));
        $this->assertClose($this->api('POST', 'ecs', ['ue_id' => $this->ue->id, 'code' => 'EC-' . $this->sfx, 'intitule' => 'Nouvel EC', 'volume_horaire' => 10]));
        $this->assertClose($this->api('PUT', "ecs/{$this->ec->id}", ['intitule' => 'Renommé']));

        // Déplacer une UE de l'année active vers l'année close est refusé aussi.
        $ueActive = Ue::factory()->pour($this->filiere, $this->active)->create(['semestre' => 1]);
        $this->assertClose($this->api('PUT', "ues/{$ueActive->id}", ['annee_id' => $this->ancienne->id]));

        $this->api('POST', 'ues', ['code' => 'OK', 'intitule' => 'Active', 'filiere_id' => $this->filiere->id, 'annee_id' => $this->active->id, 'semestre' => 1, 'volume_horaire' => 20])->assertCreated();
    }

    public function test_seances_inscriptions_et_imports_d_une_annee_close_sont_refuses(): void
    {
        $this->assertClose($this->api('POST', 'evenements', ['ec_id' => $this->ec->id, 'date' => now()->addDay()->toDateString(), 'heure_debut' => '08:00', 'heure_fin' => '10:00']));
        $this->assertClose($this->api('DELETE', "evenements/{$this->seance->id}"));
        $this->assertClose($this->api('POST', "students/{$this->etudiant->id}/ecs/reset"));
        $this->assertClose($this->api('POST', 'filieres/reconduire', ['source_annee_id' => $this->active->id, 'target_annee_id' => $this->ancienne->id]));
        $this->assertClose($this->api('POST', 'import/validate-courses', ['ues' => [[
            'code' => 'IA', 'intitule' => 'Importée', 'filiere_id' => $this->filiere->id, 'annee_id' => $this->ancienne->id, 'semestre' => 1, 'volume_horaire' => 20,
        ]]]));

        $autre = Etudiant::factory()->pour($this->filiere, $this->active)->create();
        $this->assertClose($this->api('PUT', "students/{$autre->id}", ['annee_id' => $this->ancienne->id]));

        $this->assertNotNull($this->seance->fresh());
        $this->assertSame(1, $this->etudiant->ecs()->count());
    }

    /** Les régularisations tardives passent : les présences ne sont pas figées. */
    public function test_les_presences_d_une_annee_close_restent_corrigeables(): void
    {
        $reponse = $this->api('POST', 'presence/manuelle', [
            'evenement_id' => $this->seance->id, 'etudiant_id' => $this->etudiant->id, 'motif' => 'Régularisation tardive',
        ]);

        $this->assertNotSame(409, $reponse->status(), (string) $reponse->getContent());
        $reponse->assertSuccessful();
    }

    /** Close pour qui a basculé, pas pour une faculté restée sur l'année. */
    public function test_une_faculte_restee_sur_l_annee_y_ecrit_encore(): void
    {
        $etab = Etablissement::create(['code' => 'AR' . $this->sfx, 'nom' => 'Faculté en retard', 'email' => "ar-{$this->sfx}@test.local", 'annee_active_id' => $this->ancienne->id]);
        $jeton = User::factory()->faculteAdmin($etab->id)->create(['email' => "retard-{$this->sfx}@test.local"])->createToken('t')->plainTextToken;
        $filiere = Filiere::create(['code' => 'RT' . $this->sfx, 'intitule' => 'Retard (L1)', 'niveau' => 'L1', 'etablissement_id' => $etab->id]);

        $this->api('POST', 'ues', ['code' => 'RT', 'intitule' => 'Encore ouverte', 'filiere_id' => $filiere->id, 'annee_id' => $this->ancienne->id, 'semestre' => 1, 'volume_horaire' => 20], $jeton)
            ->assertCreated();
    }
}
