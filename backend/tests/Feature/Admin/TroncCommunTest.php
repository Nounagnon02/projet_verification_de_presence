<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\Ue;
use App\Models\User;
use App\Services\Maquette\RegistreMaquette;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Cours communs : une UE suivie par plusieurs filières d'un même niveau.
 *
 * Une seule UE, des séances communes ; chaque filière y compte ses propres
 * étudiants. Un cours commun devait être dupliqué par filière.
 */
class TroncCommunTest extends TestCase
{
    private string $sfx;
    private string $jeton;
    private AnneeAcademique $annee;
    private Filiere $gl;
    private Filiere $im;
    private Filiere $l1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx = Str::upper(Str::random(5));
        $etab = Etablissement::create(['code' => 'TC' . $this->sfx, 'nom' => 'Faculté T', 'email' => "tc-{$this->sfx}@test.local"]);
        $this->jeton = User::factory()->faculteAdmin($etab->id)->create(['email' => "tronc-{$this->sfx}@test.local"])->createToken('t')->plainTextToken;

        AnneeAcademique::where('active', true)->update(['active' => false]);
        $this->annee = AnneeAcademique::create(['libelle' => '2037-2038', 'date_debut' => '2025-10-01', 'date_fin' => '2038-09-30', 'active' => true]);

        $filiere = fn (string $code, string $niveau) => Filiere::create(['code' => "{$code}{$this->sfx}", 'intitule' => "{$code} ({$niveau})", 'niveau' => $niveau, 'etablissement_id' => $etab->id]);
        $this->gl = $filiere('GL', 'L2');
        $this->im = $filiere('IM', 'L2');
        $this->l1 = $filiere('XX', 'L1');
    }

    private function api(string $methode, string $uri, array $donnees = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->jeton)->json($methode, "/api/admin/{$uri}", $donnees);
    }

    /** UE commune à GL et IM, avec un EC. */
    private function coursCommun(): Ec
    {
        $ue = Ue::create(['code' => 'TCOM' . $this->sfx, 'intitule' => 'Technique de résolution de problèmes', 'filiere_id' => $this->gl->id, 'annee_id' => $this->annee->id, 'semestre' => 4, 'volume_horaire' => 0]);
        app(RegistreMaquette::class)->synchroniserFilieres($ue, [$this->im->id]);

        return Ec::create(['ue_id' => $ue->id, 'code' => 'ECOM' . $this->sfx, 'intitule' => 'TRPR', 'volume_cm' => 40]);
    }

    public function test_une_ue_commune_se_cree_pour_des_filieres_d_un_meme_niveau(): void
    {
        $donnees = ['code' => 'TC1' . $this->sfx, 'intitule' => 'Cours commun', 'filiere_id' => $this->gl->id, 'annee_id' => $this->annee->id, 'semestre' => 3];

        $codes = collect($this->api('POST', 'ues', $donnees + ['filiere_ids' => [$this->im->id]])->assertCreated()->json('data.filieres'))->pluck('code')->sort()->values()->all();
        $this->assertSame([$this->gl->code, $this->im->code], $codes);

        $this->api('POST', 'ues', ['code' => 'TC2' . $this->sfx] + $donnees + ['filiere_ids' => [$this->l1->id]])
            ->assertStatus(422)->assertJsonValidationErrors('filiere_ids');
    }

    public function test_les_etudiants_de_chaque_filiere_sont_inscrits_au_cours_commun(): void
    {
        $ec = $this->coursCommun();
        $etudiantGl = Etudiant::factory()->pour($this->gl, $this->annee)->create();
        $etudiantIm = Etudiant::factory()->pour($this->im, $this->annee)->create();
        $etudiantGl->autoEnroll();
        $etudiantIm->autoEnroll();

        $this->assertTrue($etudiantGl->ecs()->where('ec_id', $ec->id)->exists());
        $this->assertTrue($etudiantIm->ecs()->where('ec_id', $ec->id)->exists());

        // La liste des UE d'IM montre le cours commun.
        $codes = collect($this->api('GET', "ues?filiere_id={$this->im->id}")->assertOk()->json('data'))->pluck('code');
        $this->assertContains('TCOM' . $this->sfx, $codes);
    }

    /** Une séance commune, une présence côté GL : chaque filière compte les siens. */
    public function test_une_seance_commune_compte_pour_chaque_filiere_ses_seuls_etudiants(): void
    {
        $ec = $this->coursCommun();
        $etudiantGl = Etudiant::factory()->pour($this->gl, $this->annee)->create();
        $etudiantIm = Etudiant::factory()->pour($this->im, $this->annee)->create();
        $etudiantGl->autoEnroll();
        $etudiantIm->autoEnroll();

        $seance = Evenement::factory()->pourEc($ec)->passe()->create();
        Presence::factory()->create(['etudiant_id' => $etudiantGl->id, 'evenement_id' => $seance->id]);

        $lignes = collect($this->api('GET', "reports/filiere-stats?annee_id={$this->annee->id}")->assertOk()->json('data'))->keyBy('code');

        $this->assertSame([1, 1, 100.0, 1], [$lignes[$this->gl->code]['presences_attendues'], $lignes[$this->gl->code]['total_presences'], (float) $lignes[$this->gl->code]['taux'], $lignes[$this->gl->code]['total_evenements']]);
        $this->assertSame([1, 0, 0.0, 1], [$lignes[$this->im->code]['presences_attendues'], $lignes[$this->im->code]['total_presences'], (float) $lignes[$this->im->code]['taux'], $lignes[$this->im->code]['total_evenements']]);
    }

    public function test_un_etudiant_sans_inscription_est_attendu_si_sa_filiere_suit_le_cours(): void
    {
        $ec = $this->coursCommun();
        $seance = Evenement::factory()->pourEc($ec)->create();

        $this->assertTrue(Etudiant::factory()->pour($this->im, $this->annee)->create()->peutAssisterA($seance));
        $this->assertFalse(Etudiant::factory()->pour($this->l1, $this->annee)->create()->peutAssisterA($seance));
    }

    /** Même code et même intitulé pour une autre filière du niveau : le cours commun, pas une copie. */
    public function test_l_import_rattache_une_filiere_au_cours_commun_au_lieu_de_le_dupliquer(): void
    {
        $this->coursCommun();
        $autre = Filiere::create(['code' => 'SI' . $this->sfx, 'intitule' => 'SI (L2)', 'niveau' => 'L2', 'etablissement_id' => $this->gl->etablissement_id]);
        $entete = 'code_ue,intitule_ue,filiere_code,niveau,annee_libelle,semestre,credits_ue,code_ec,intitule_ec,volume_cm,volume_td,volume_tp,volume_td_tp';

        $reponse = $this->api('POST', 'import/csv/courses', ['file' => UploadedFile::fake()->createWithContent('cours.csv',
            "{$entete}\nTCOM{$this->sfx},Technique de résolution de problèmes,{$autre->code},L2,2037-2038,4,4,ECOM{$this->sfx},TRPR,40,0,0,0\n"
            . "TCOM{$this->sfx},Un autre cours,{$this->l1->code},L1,2037-2038,1,4,EX{$this->sfx},EC,10,0,0,0\n"
        )])->assertOk();

        $this->assertSame(1, Ue::where('code', 'TCOM' . $this->sfx)->count(), 'Une seule UE, pas de copie.');
        $this->assertTrue(Ue::where('code', 'TCOM' . $this->sfx)->first()->filieres()->where('filieres.id', $autre->id)->exists());
        $this->assertSame(1, Ec::where('code', 'ECOM' . $this->sfx)->count());
        $this->assertCount(1, $reponse->json('data.errors'), 'Autre intitulé sous le même code : refusé.');
    }
}
