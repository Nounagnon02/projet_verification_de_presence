<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\Programme;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Filières, programmes et niveaux.
 *
 * Une filière est un programme à un niveau (IM + L2 = IM-L2). Le niveau était
 * un texte libre, saisi trois fois ; le code d'une filière était unique dans
 * toute la base ; les UE ne dépassaient pas le semestre 6.
 */
class FilieresProgrammesTest extends TestCase
{
    private string $sfx;
    private Etablissement $etabA;
    private Etablissement $etabB;
    private string $jetonA;
    private AnneeAcademique $annee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx = Str::upper(Str::random(4));
        $this->etabA = Etablissement::create(['code' => 'PA' . $this->sfx, 'nom' => 'Faculté A', 'email' => "pa-{$this->sfx}@test.local"]);
        $this->etabB = Etablissement::create(['code' => 'PB' . $this->sfx, 'nom' => 'Faculté B', 'email' => "pb-{$this->sfx}@test.local"]);

        // Une seule année active, celle de l'établissement A.
        AnneeAcademique::query()->update(['active' => false]);
        $this->annee = AnneeAcademique::create([
            'libelle' => 'P' . $this->sfx, 'date_debut' => '2097-09-01', 'date_fin' => '2098-07-31',
            'active' => true, 'etablissement_id' => $this->etabA->id,
        ]);

        $this->jetonA = User::factory()->faculteAdmin($this->etabA->id)
            ->create(['email' => "programmes-a-{$this->sfx}@test.local"])
            ->createToken('t')->plainTextToken;
    }

    private function filiere(Etablissement $etab, string $code, string $niveau): Filiere
    {
        return Filiere::create(['code' => $code, 'intitule' => "Filière {$code}", 'niveau' => $niveau, 'etablissement_id' => $etab->id]);
    }

    // ── Niveaux ─────────────────────────────────────────────────────────

    public function test_les_niveaux_officiels_viennent_du_serveur(): void
    {
        $this->withToken($this->jetonA)->getJson('/api/admin/niveaux')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0', ['code' => 'L1', 'libelle' => 'Licence 1', 'semestres' => [1, 2]])
            ->assertJsonPath('data.4.code', 'M2')
            ->assertJsonPath('data.4.semestres', [9, 10]);
    }

    public function test_un_niveau_hors_liste_est_refuse(): void
    {
        $this->withToken($this->jetonA)->postJson('/api/admin/filieres', [
            'code' => "X{$this->sfx}", 'intitule' => 'Filière libre', 'niveau' => 'Licence 1',
        ])->assertStatus(422)->assertJsonValidationErrors(['niveau']);
    }

    // ── Programmes ──────────────────────────────────────────────────────

    public function test_une_filiere_se_cree_a_partir_de_son_programme_et_de_son_niveau(): void
    {
        $reponse = $this->withToken($this->jetonA)->postJson('/api/admin/filieres', [
            'programme_code' => "im{$this->sfx}", 'programme_intitule' => 'Informatique et Mathématiques', 'niveau' => 'L2',
        ])->assertCreated()
            ->assertJsonPath('data.code', "IM{$this->sfx}-L2")
            ->assertJsonPath('data.intitule', 'Informatique et Mathématiques (L2)')
            ->assertJsonPath('data.programme.code', "IM{$this->sfx}");

        $filiere = Filiere::findOrFail($reponse->json('data.id'));

        $this->assertSame($this->etabA->id, $filiere->etablissement_id);
        // Rattachée à l'année active de SON établissement.
        $this->assertDatabaseHas('filiere_annee', ['filiere_id' => $filiere->id, 'annee_id' => $this->annee->id]);

        // Un second niveau réutilise le programme.
        $this->withToken($this->jetonA)->postJson('/api/admin/filieres', [
            'programme_id' => $filiere->programme_id, 'niveau' => 'L3',
        ])->assertCreated()->assertJsonPath('data.code', "IM{$this->sfx}-L3");

        $this->assertSame(1, Programme::where('code', "IM{$this->sfx}")->count());
    }

    public function test_sans_programme_il_est_deduit_du_code(): void
    {
        $this->withToken($this->jetonA)->postJson('/api/admin/filieres', [
            'code' => "G{$this->sfx}-L1", 'intitule' => 'Gestion (L1)', 'niveau' => 'L1',
        ])->assertCreated()
            ->assertJsonPath('data.programme.code', "G{$this->sfx}")
            ->assertJsonPath('data.programme.intitule', 'Gestion');
    }

    public function test_les_programmes_sont_cloisonnes(): void
    {
        Programme::create(['etablissement_id' => $this->etabA->id, 'code' => "A{$this->sfx}", 'intitule' => 'Programme A']);
        Programme::create(['etablissement_id' => $this->etabB->id, 'code' => "B{$this->sfx}", 'intitule' => 'Programme B']);

        $codes = collect($this->withToken($this->jetonA)->getJson('/api/admin/programmes')->assertOk()->json('data'))->pluck('code');

        $this->assertContains("A{$this->sfx}", $codes);
        $this->assertNotContains("B{$this->sfx}", $codes);
    }

    // ── Code unique dans l'établissement ────────────────────────────────

    public function test_le_code_n_est_unique_que_dans_l_etablissement(): void
    {
        $code = "C{$this->sfx}-L1";
        $this->filiere($this->etabB, $code, 'L1');

        // La faculté B a déjà ce code : la faculté A peut l'avoir aussi.
        $this->withToken($this->jetonA)->postJson('/api/admin/filieres', [
            'code' => $code, 'intitule' => 'Homonyme (L1)', 'niveau' => 'L1',
        ])->assertCreated();

        // Mais pas deux fois.
        $this->withToken($this->jetonA)->postJson('/api/admin/filieres', [
            'code' => strtolower($code), 'intitule' => 'Doublon (L1)', 'niveau' => 'L1',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    // ── Niveau verrouillé par les UE ────────────────────────────────────

    public function test_le_niveau_d_une_filiere_qui_a_des_ue_ne_change_plus(): void
    {
        $avecUe = $this->filiere($this->etabA, "U{$this->sfx}-L1", 'L1');
        Ue::create(['code' => "UE{$this->sfx}", 'intitule' => 'UE', 'filiere_id' => $avecUe->id, 'annee_id' => $this->annee->id, 'semestre' => 1, 'volume_horaire' => 30]);

        $reponse = $this->withToken($this->jetonA)->putJson("/api/admin/filieres/{$avecUe->id}", ['niveau' => 'L2'])
            ->assertStatus(422)->assertJsonValidationErrors(['niveau']);
        $this->assertStringContainsString('S1', $reponse->json('errors.niveau.0'));
        $this->assertSame('L1', $avecUe->fresh()->niveau);

        // Sans UE, corriger une erreur de saisie reste possible.
        $vide = $this->filiere($this->etabA, "V{$this->sfx}-L2", 'L2');
        $this->withToken($this->jetonA)->putJson("/api/admin/filieres/{$vide->id}", ['niveau' => 'L1'])->assertOk();
    }

    // ── Effectifs de l'année ────────────────────────────────────────────

    public function test_les_effectifs_sont_ceux_de_l_annee_choisie(): void
    {
        $filiere = $this->filiere($this->etabA, "E{$this->sfx}-L1", 'L1');
        $autreAnnee = AnneeAcademique::create([
            'libelle' => 'Q' . $this->sfx, 'date_debut' => '2098-09-01', 'date_fin' => '2099-07-31',
            'active' => false, 'etablissement_id' => $this->etabA->id,
        ]);
        Etudiant::factory()->create(['filiere_id' => $filiere->id, 'annee_id' => $this->annee->id]);
        Etudiant::factory()->create(['filiere_id' => $filiere->id, 'annee_id' => $autreAnnee->id]);
        foreach ([1, 1, 2] as $i => $semestre) {
            Ue::create(['code' => "E{$this->sfx}{$i}", 'intitule' => "UE {$i}", 'filiere_id' => $filiere->id, 'annee_id' => $this->annee->id, 'semestre' => $semestre, 'volume_horaire' => 30]);
        }

        $ligne = collect($this->withToken($this->jetonA)->getJson('/api/admin/filieres?annee_id=' . $this->annee->id)->assertOk()->json('data'))
            ->firstWhere('id', $filiere->id);

        $this->assertSame(1, $ligne['etudiants_count']);
        $this->assertSame(2, $ligne['etudiants_total']);
        $this->assertSame(3, $ligne['ues_count']);
        $this->assertSame(['1' => 2, '2' => 1], $ligne['ues_par_semestre']);
    }

    // ── UE : semestres et codes ─────────────────────────────────────────

    public function test_une_ue_de_master_se_cree_et_se_modifie(): void
    {
        $master = $this->filiere($this->etabA, "M{$this->sfx}-M1", 'M1');

        $id = $this->withToken($this->jetonA)->postJson('/api/admin/ues', [
            'code' => "UEM{$this->sfx}", 'intitule' => 'UE de Master', 'filiere_id' => $master->id,
            'annee_id' => $this->annee->id, 'semestre' => 7, 'volume_horaire' => 30,
        ])->assertCreated()->json('data.id');

        // Le formulaire renvoie le semestre 7 tel quel : il était refusé.
        $this->withToken($this->jetonA)->putJson("/api/admin/ues/{$id}", ['intitule' => 'UE de Master revue', 'semestre' => 7])
            ->assertOk()->assertJsonPath('data.intitule', 'UE de Master revue');
    }

    public function test_un_semestre_incompatible_avec_le_niveau_est_refuse(): void
    {
        $licence = $this->filiere($this->etabA, "S{$this->sfx}-L1", 'L1');

        $this->withToken($this->jetonA)->postJson('/api/admin/ues', [
            'code' => "UES{$this->sfx}", 'intitule' => 'UE', 'filiere_id' => $licence->id,
            'annee_id' => $this->annee->id, 'semestre' => 3, 'volume_horaire' => 30,
        ])->assertStatus(422)->assertJsonValidationErrors(['semestre']);
    }

    /**
     * Un code d'UE n'est porté qu'une fois par année dans un établissement :
     * une seconde filière ne crée pas une seconde UE de même code. Un cours
     * commun à plusieurs filières sera une seule UE rattachée à chacune.
     */
    public function test_un_code_d_ue_n_est_porte_qu_une_fois_par_annee_dans_l_etablissement(): void
    {
        $x = $this->filiere($this->etabA, "X{$this->sfx}-L1", 'L1');
        $y = $this->filiere($this->etabA, "Y{$this->sfx}-L1", 'L1');
        $ue = fn (Filiere $f) => [
            'code' => "TC{$this->sfx}", 'intitule' => 'Tronc commun', 'filiere_id' => $f->id,
            'annee_id' => $this->annee->id, 'semestre' => 1, 'volume_horaire' => 30,
        ];

        $this->withToken($this->jetonA)->postJson('/api/admin/ues', $ue($x))->assertCreated();
        $this->withToken($this->jetonA)->postJson('/api/admin/ues', $ue($y))->assertStatus(422)->assertJsonValidationErrors(['code']);
        $this->withToken($this->jetonA)->postJson('/api/admin/ues', $ue($x))->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_une_ue_ne_se_cree_pas_dans_la_filiere_d_un_autre_etablissement(): void
    {
        $ailleurs = $this->filiere($this->etabB, "B{$this->sfx}-L1", 'L1');

        $this->withToken($this->jetonA)->postJson('/api/admin/ues', [
            'code' => "UEB{$this->sfx}", 'intitule' => 'UE', 'filiere_id' => $ailleurs->id,
            'annee_id' => $this->annee->id, 'semestre' => 1, 'volume_horaire' => 30,
        ])->assertStatus(404);

        $this->assertDatabaseMissing('ues', ['code' => "UEB{$this->sfx}"]);
    }
}
