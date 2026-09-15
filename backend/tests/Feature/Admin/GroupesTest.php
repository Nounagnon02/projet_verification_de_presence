<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Groupe;
use App\Models\Ue;
use App\Models\User;
use App\Services\AttendanceRateService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Groupes de TD et de TP d'une promotion.
 *
 * Une séance de groupe n'attend que ses membres ; chaque groupe reçoit tout le
 * volume de TD ou de TP de l'EC ; un étudiant a un groupe de TD et un groupe de
 * TP au plus pour une année.
 */
class GroupesTest extends TestCase
{
    private string $sfx;
    private string $jeton;
    private AnneeAcademique $annee;
    private Filiere $filiere;
    private Ec $ec;
    /** @var list<Etudiant> */
    private array $etudiants = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx = Str::upper(Str::random(5));
        $etab = Etablissement::create(['code' => 'GR' . $this->sfx, 'nom' => 'Faculté G', 'email' => "gr-{$this->sfx}@test.local"]);
        $this->jeton = User::factory()->faculteAdmin($etab->id)->create(['email' => "groupes-{$this->sfx}@test.local"])->createToken('t')->plainTextToken;

        AnneeAcademique::where('active', true)->update(['active' => false]);
        $this->annee = AnneeAcademique::create(['libelle' => '2039-2040', 'date_debut' => '2025-10-01', 'date_fin' => '2040-09-30', 'active' => true]);
        $this->filiere = Filiere::create(['code' => 'GR' . $this->sfx, 'intitule' => 'Groupes (L2)', 'niveau' => 'L2', 'etablissement_id' => $etab->id]);

        $ue = Ue::create(['code' => 'UG' . $this->sfx, 'intitule' => 'UE', 'filiere_id' => $this->filiere->id, 'annee_id' => $this->annee->id, 'semestre' => 3, 'volume_horaire' => 0]);
        $this->ec = Ec::create(['ue_id' => $ue->id, 'code' => 'EG' . $this->sfx, 'intitule' => 'EC', 'volume_cm' => 10, 'volume_td' => 4]);

        foreach (['M1', 'M2', 'M3', 'M4'] as $matricule) {
            $etudiant = Etudiant::factory()->pour($this->filiere, $this->annee)->create(['matricule' => "{$matricule}-{$this->sfx}"]);
            $etudiant->autoEnroll();
            $this->etudiants[] = $etudiant;
        }
    }

    private function api(string $methode, string $uri, array $donnees = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->jeton)->json($methode, "/api/admin/{$uri}", $donnees);
    }

    private function repartir(int $nombre, string $type = 'td'): TestResponse
    {
        return $this->api('POST', 'groupes/repartir', ['filiere_id' => $this->filiere->id, 'annee_id' => $this->annee->id, 'type' => $type, 'nombre' => $nombre]);
    }

    private function groupe(string $libelle, string $type = 'td'): Groupe
    {
        return Groupe::where(['filiere_id' => $this->filiere->id, 'type' => $type, 'libelle' => $libelle])->firstOrFail();
    }

    private function seance(string $type, ?Groupe $groupe, string $debut, string $fin): TestResponse
    {
        return $this->api('POST', 'evenements', [
            'ec_id' => $this->ec->id, 'type_cours' => $type, 'groupe_id' => $groupe?->id,
            'date' => today()->addDay()->toDateString(), 'heure_debut' => $debut, 'heure_fin' => $fin,
        ]);
    }

    public function test_repartir_la_promotion_par_matricule_en_groupes_de_meme_taille(): void
    {
        $this->repartir(2)->assertOk()->assertJsonPath('data.effectifs', ['G1' => 2, 'G2' => 2]);
        $this->assertSame(['M1', 'M2'], $this->groupe('G1')->etudiants()->orderBy('matricule')->pluck('matricule')->map(fn ($m) => substr($m, 0, 2))->all());

        // Une nouvelle répartition remplace la précédente, sans doublon.
        $this->repartir(3)->assertOk()->assertJsonPath('data.effectifs', ['G1' => 2, 'G2' => 1, 'G3' => 1]);
        $this->assertSame(4, \Illuminate\Support\Facades\DB::table('etudiant_groupe')->where('annee_id', $this->annee->id)->where('type', 'td')->count());
    }

    public function test_affecter_un_etudiant_a_un_autre_groupe(): void
    {
        $this->repartir(2);
        $etudiant = $this->etudiants[0];

        $this->api('PUT', "students/{$etudiant->id}/groupes", ['td' => $this->groupe('G2')->id])->assertOk();
        $this->assertSame(['G2'], $etudiant->groupes()->pluck('libelle')->all(), 'Un seul groupe de TD par année.');

        // Un groupe de TD ne s'affecte pas comme groupe de TP.
        $this->api('PUT', "students/{$etudiant->id}/groupes", ['tp' => $this->groupe('G1')->id])->assertStatus(422);
    }

    public function test_une_seance_de_groupe_n_attend_que_ses_membres(): void
    {
        $this->repartir(2);
        $g1 = $this->groupe('G1');
        $seance = Evenement::factory()->pourEc($this->ec)->passe()->create(['type_cours' => 'td', 'groupe_id' => $g1->id]);

        $attendus = app(AttendanceRateService::class)->expected(fn ($q) => $q->where('e.id', $seance->id));
        $this->assertSame(2, $attendus);

        $this->assertTrue($this->etudiants[0]->peutAssisterA($seance));
        $this->assertFalse($this->etudiants[3]->peutAssisterA($seance));
    }

    /** Chaque groupe reçoit tout le volume de TD ; une séance de promotion compte pour chacun. */
    public function test_le_volume_de_td_se_compte_groupe_par_groupe(): void
    {
        $this->repartir(2);
        [$g1, $g2] = [$this->groupe('G1'), $this->groupe('G2')];

        $this->seance('td', $g1, '08:00', '10:00')->assertCreated();
        $this->seance('td', $g1, '10:00', '12:00')->assertCreated();
        $this->seance('td', $g1, '14:00', '15:00')->assertStatus(422)
            ->assertJsonFragment(['message' => "L'EC {$this->ec->code} (EC) a épuisé son volume de TD du groupe G1 (4h) : aucune séance de TD supplémentaire ne peut être programmée."]);

        $this->seance('td', $g2, '14:00', '16:00')->assertCreated();

        // Toute la promotion : G1 a déjà ses 4 heures, il n'en reste pour personne.
        $this->seance('td', null, '16:00', '17:00')->assertStatus(422);
    }

    public function test_un_groupe_ne_vise_qu_une_seance_de_son_type(): void
    {
        $this->repartir(2);
        $this->repartir(1, 'tp');

        $this->seance('cm', $this->groupe('G1'), '08:00', '10:00')->assertStatus(422)->assertJsonValidationErrors('groupe_id');
        $this->seance('tp', $this->groupe('G1'), '08:00', '10:00')->assertStatus(422)->assertJsonValidationErrors('groupe_id');
    }

    public function test_un_groupe_qui_a_des_seances_ne_se_supprime_pas(): void
    {
        $this->repartir(2);
        $g1 = $this->groupe('G1');
        Evenement::factory()->pourEc($this->ec)->create(['type_cours' => 'td', 'groupe_id' => $g1->id]);

        $this->api('DELETE', "groupes/{$g1->id}")->assertStatus(409);
        $this->api('DELETE', "groupes/{$this->groupe('G2')->id}")->assertOk();
    }

    /** Un nouveau niveau, ce sont de nouveaux groupes. */
    public function test_changer_de_filiere_retire_l_etudiant_de_ses_groupes(): void
    {
        $this->repartir(2);
        $this->repartir(1, 'tp');
        $autre = Filiere::create(['code' => 'GX' . $this->sfx, 'intitule' => 'Autre (L2)', 'niveau' => 'L2', 'etablissement_id' => $this->filiere->etablissement_id]);

        $this->api('PUT', "students/{$this->etudiants[0]->id}", ['filiere_id' => $autre->id])->assertOk();

        $this->assertSame(0, $this->etudiants[0]->groupes()->count());
        $this->assertSame(2, $this->etudiants[1]->groupes()->count(), 'Les autres étudiants gardent leurs groupes.');
    }

    public function test_l_import_des_etudiants_place_chacun_dans_ses_groupes(): void
    {
        $csv = "nom,prenom,matricule,filiere,annee,email,groupe_td,groupe_tp\n"
            . "DOSSOU,Ama,IMP-{$this->sfx},{$this->filiere->code},2039-2040,imp-{$this->sfx}@test.local,g3,TP1\n";

        $this->api('POST', 'import/students', ['file' => UploadedFile::fake()->createWithContent('etudiants.csv', $csv)])
            ->assertOk()->assertJsonPath('data.success', 1);

        $etudiant = Etudiant::where('matricule', "IMP-{$this->sfx}")->firstOrFail();
        $this->assertEqualsCanonicalizing(['td:G3', 'tp:TP1'], $etudiant->groupes->map(fn ($g) => "{$g->type}:{$g->libelle}")->all());
    }
}
