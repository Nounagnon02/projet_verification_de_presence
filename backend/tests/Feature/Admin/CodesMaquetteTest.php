<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Filiere;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Un code d'UE, comme un code d'EC, est unique dans une année et un
 * établissement, et se réutilise d'une année à l'autre. Le formulaire, l'import
 * CSV et l'import IA appliquent la même règle.
 */
class CodesMaquetteTest extends TestCase
{
    private string $sfx;
    private string $jetonA;
    private string $jetonB;
    private AnneeAcademique $y1;
    private AnneeAcademique $y2;
    private Filiere $f1;
    private Filiere $f2;
    private Filiere $fb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx = Str::upper(Str::random(5));
        $etabA = Etablissement::create(['code' => 'CA' . $this->sfx, 'nom' => 'Faculté A', 'email' => "ca-{$this->sfx}@test.local"]);
        $etabB = Etablissement::create(['code' => 'CB' . $this->sfx, 'nom' => 'Faculté B', 'email' => "cb-{$this->sfx}@test.local"]);
        $this->jetonA = User::factory()->faculteAdmin($etabA->id)->create(['email' => "codes-a-{$this->sfx}@test.local"])->createToken('t')->plainTextToken;
        $this->jetonB = User::factory()->faculteAdmin($etabB->id)->create(['email' => "codes-b-{$this->sfx}@test.local"])->createToken('t')->plainTextToken;

        AnneeAcademique::where('active', true)->update(['active' => false]);
        $this->y1 = AnneeAcademique::create(['libelle' => '2031-2032', 'date_debut' => '2031-10-01', 'date_fin' => '2032-09-30', 'active' => true]);
        $this->y2 = AnneeAcademique::create(['libelle' => '2032-2033', 'date_debut' => '2032-10-01', 'date_fin' => '2033-09-30']);

        $this->f1 = Filiere::create(['code' => 'F1' . $this->sfx, 'intitule' => 'Filière 1 (L1)', 'niveau' => 'L1', 'etablissement_id' => $etabA->id]);
        $this->f2 = Filiere::create(['code' => 'F2' . $this->sfx, 'intitule' => 'Filière 2 (L1)', 'niveau' => 'L1', 'etablissement_id' => $etabA->id]);
        $this->fb = Filiere::create(['code' => 'FB' . $this->sfx, 'intitule' => 'Filière B (L1)', 'niveau' => 'L1', 'etablissement_id' => $etabB->id]);
    }

    private function api(string $methode, string $uri, array $donnees = [], ?string $jeton = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($jeton ?? $this->jetonA)->json($methode, "/api/admin/{$uri}", $donnees);
    }

    private function ue(Filiere $filiere, AnneeAcademique $annee, string $code): Ue
    {
        return Ue::create(['code' => $code, 'intitule' => "UE {$code}", 'filiere_id' => $filiere->id, 'annee_id' => $annee->id, 'semestre' => 1, 'volume_horaire' => 20]);
    }

    private function nouvelleUe(Filiere $filiere, AnneeAcademique $annee, string $code, ?string $jeton = null): TestResponse
    {
        return $this->api('POST', 'ues', ['code' => $code, 'intitule' => "UE {$code}", 'filiere_id' => $filiere->id, 'annee_id' => $annee->id, 'semestre' => 1, 'volume_horaire' => 20], $jeton);
    }

    public function test_un_code_d_ue_se_reutilise_d_une_annee_a_l_autre_mais_pas_dans_la_meme(): void
    {
        $this->nouvelleUe($this->f1, $this->y1, 'MTH101')->assertCreated();
        $this->nouvelleUe($this->f1, $this->y2, 'MTH101')->assertCreated();

        $this->nouvelleUe($this->f2, $this->y1, 'mth101')
            ->assertStatus(422)
            ->assertJsonFragment(['code' => ["Le code mth101 est déjà utilisé en 2031-2032 par l'UE « UE MTH101 » de {$this->f1->code}."]]);

        // Une autre faculté porte ses propres codes.
        $this->nouvelleUe($this->fb, $this->y1, 'MTH101', $this->jetonB)->assertCreated();
    }

    public function test_un_code_d_ec_se_reutilise_d_une_annee_a_l_autre_mais_pas_dans_la_meme(): void
    {
        $ue1 = $this->ue($this->f1, $this->y1, 'U1');
        $ue2 = $this->ue($this->f1, $this->y2, 'U1');
        $ue3 = $this->ue($this->f2, $this->y1, 'U3');

        $this->api('POST', 'ecs', ['ue_id' => $ue1->id, 'code' => 'ALG', 'intitule' => 'Algèbre', 'volume_horaire' => 20])->assertCreated();
        $this->api('POST', 'ecs', ['ue_id' => $ue2->id, 'code' => 'ALG', 'intitule' => 'Algèbre', 'volume_horaire' => 20])->assertCreated();
        $this->api('POST', 'ecs', ['ue_id' => $ue3->id, 'code' => 'alg', 'intitule' => 'Autre', 'volume_horaire' => 20])
            ->assertStatus(422)->assertJsonValidationErrors('code');
    }

    /** Le 422 constaté sur les EC reconduits en 2026-2027. */
    public function test_un_ec_reconduit_reste_modifiable_et_son_code_peut_changer(): void
    {
        Ec::create(['ue_id' => $this->ue($this->f1, $this->y1, 'U1')->id, 'code' => 'ALG', 'intitule' => 'Algèbre', 'volume_horaire' => 20]);
        $ue = $this->ue($this->f1, $this->y2, 'U1');
        $reconduit = Ec::create(['ue_id' => $ue->id, 'code' => 'ALG', 'intitule' => 'Algèbre', 'volume_horaire' => 20]);

        $this->api('PUT', "ecs/{$reconduit->id}", ['ue_id' => $ue->id, 'code' => 'ALG', 'intitule' => 'Algèbre linéaire', 'volume_horaire' => 30])->assertOk();
        $this->assertSame('Algèbre linéaire', $reconduit->fresh()->intitule);

        $this->api('PUT', "ecs/{$reconduit->id}", ['code' => 'ALG-2'])->assertOk();
        $this->assertSame('ALG-2', $reconduit->fresh()->code);
    }

    public function test_un_ec_ne_passe_pas_dans_l_ue_d_une_autre_faculte(): void
    {
        $ec = Ec::create(['ue_id' => $this->ue($this->f1, $this->y1, 'U1')->id, 'code' => 'EC1', 'intitule' => 'EC', 'volume_horaire' => 20]);
        $etrangere = $this->ue($this->fb, $this->y1, 'UB');

        $this->api('PUT', "ecs/{$ec->id}", ['ue_id' => $etrangere->id])->assertNotFound();
        $this->assertSame($ec->ue_id, $ec->fresh()->ue_id);
    }

    public function test_l_import_csv_applique_les_regles_du_formulaire(): void
    {
        $this->ue($this->f2, $this->y1, 'CSVX');
        $entete = 'code_ue,intitule_ue,filiere_code,niveau,annee_libelle,semestre,volume_horaire_ue,code_ec,intitule_ec,volume_horaire_ec';

        $reponse = $this->api('POST', 'import/csv/courses', ['file' => UploadedFile::fake()->createWithContent('cours.csv',
            "{$entete}\nCSVX,Pris ailleurs,{$this->f1->code},L1,2031-2032,1,20,CX1,EC,20\n"
            . "CSVY,Nouvelle,{$this->f1->code},L1,2031-2032,1,20,CY1,EC,20\n"
        )])->assertOk();

        $this->assertStringContainsString("déjà utilisé en 2031-2032 par l'UE", $reponse->json('data.errors.0.error'));
        $this->assertSame(1, Ue::where('code', 'CSVX')->where('annee_id', $this->y1->id)->count());
        $this->assertDatabaseHas('ues', ['code' => 'CSVY', 'filiere_id' => $this->f1->id]);

        // Rejouer la même UE la complète, sans doublon.
        $this->api('POST', 'import/csv/courses', ['file' => UploadedFile::fake()->createWithContent('cours.csv',
            "{$entete}\nCSVY,Nouvelle (corrigée),{$this->f1->code},L1,2031-2032,1,20,CY1,EC,20\n"
        )])->assertOk();
        $this->assertSame(['Nouvelle (corrigée)'], Ue::where('code', 'CSVY')->pluck('intitule')->all());
    }

    public function test_l_import_ia_refuse_tout_le_lot_si_un_code_est_pris(): void
    {
        // Pris par un AUTRE cours : même code et même intitulé désigneraient un
        // cours commun, que l'import rattache (TroncCommunTest).
        Ue::create(['code' => 'IAX', 'intitule' => 'Un autre cours', 'filiere_id' => $this->f2->id, 'annee_id' => $this->y1->id, 'semestre' => 1, 'volume_horaire' => 20]);
        $ue = fn (string $code) => ['code' => $code, 'intitule' => "UE {$code}", 'filiere_id' => $this->f1->id, 'annee_id' => $this->y1->id, 'semestre' => 1, 'volume_horaire' => 20,
            'ecs' => [['code' => "{$code}-1", 'intitule' => 'EC', 'volume_horaire' => 20]]];

        $this->api('POST', 'import/validate-courses', ['ues' => [$ue('IAOK'), $ue('IAX')]])
            ->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        $this->assertDatabaseMissing('ues', ['code' => 'IAOK']);
    }

    public function test_l_annee_des_ec_suit_celle_de_leur_ue(): void
    {
        $ue = $this->ue($this->f1, $this->y1, 'U1');
        $ec = Ec::create(['ue_id' => $ue->id, 'code' => 'EC1', 'intitule' => 'EC', 'volume_horaire' => 20]);
        $this->assertSame($this->y1->id, (int) $ec->fresh()->annee_id);

        $ue->update(['annee_id' => $this->y2->id]);
        $this->assertSame($this->y2->id, (int) $ec->fresh()->annee_id);
    }
}
