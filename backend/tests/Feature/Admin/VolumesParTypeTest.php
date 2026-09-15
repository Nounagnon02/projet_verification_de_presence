<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Etablissement;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Volumes d'un EC par type de séance (CM, TD, TP, réserve TP/TD).
 *
 * Un EC n'avait qu'un volume : une séance de TD consommait les heures du cours
 * magistral. Les TD et les TP puisent dans la réserve TP/TD — les maquettes qui
 * ne séparent pas les deux — une fois leur propre volume épuisé ; une évaluation
 * ne consomme rien ; un EC « à ventiler » garde son total.
 */
class VolumesParTypeTest extends TestCase
{
    private string $sfx;
    private string $jeton;
    private AnneeAcademique $annee;
    private Filiere $filiere;
    private Ue $ue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx = Str::upper(Str::random(5));
        $etab = Etablissement::create(['code' => 'VT' . $this->sfx, 'nom' => 'Faculté V', 'email' => "vt-{$this->sfx}@test.local"]);
        $this->jeton = User::factory()->faculteAdmin($etab->id)->create(['email' => "volumes-{$this->sfx}@test.local"])->createToken('t')->plainTextToken;

        AnneeAcademique::where('active', true)->update(['active' => false]);
        $this->annee = AnneeAcademique::create(['libelle' => '2034-2035', 'date_debut' => '2025-10-01', 'date_fin' => '2035-09-30', 'active' => true]);
        $this->filiere = Filiere::create(['code' => 'V' . $this->sfx, 'intitule' => 'Volumes (L1)', 'niveau' => 'L1', 'etablissement_id' => $etab->id]);
        $this->ue = Ue::create(['code' => 'UV' . $this->sfx, 'intitule' => 'UE', 'filiere_id' => $this->filiere->id, 'annee_id' => $this->annee->id, 'semestre' => 1, 'volume_horaire' => 0]);
    }

    private function ec(array $volumes): Ec
    {
        return Ec::create(['ue_id' => $this->ue->id, 'code' => 'EC' . Str::upper(Str::random(4)), 'intitule' => 'EC'] + $volumes);
    }

    private function seance(Ec $ec, string $type, string $debut, string $fin, int $jour = 1): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->jeton)->postJson('/api/admin/evenements', [
            'ec_id' => $ec->id, 'type_cours' => $type, 'date' => today()->addDays($jour)->toDateString(), 'heure_debut' => $debut, 'heure_fin' => $fin,
        ]);
    }

    public function test_le_total_d_un_ec_ventile_est_la_somme_de_ses_types(): void
    {
        $ec = $this->ec(['volume_cm' => 10, 'volume_td' => 5, 'volume_tp' => 0, 'volume_td_tp' => 15]);

        $this->assertSame(30, $ec->volume_horaire);
        $this->assertFalse($ec->volume_a_ventiler);
        $this->assertSame(30, (int) $this->ue->fresh()->volume_horaire, "Le volume de l'UE suit ses EC.");
    }

    public function test_un_cm_ne_consomme_que_le_volume_de_cm(): void
    {
        $ec = $this->ec(['volume_cm' => 4, 'volume_td' => 2]);

        $this->seance($ec, 'cm', '08:00', '10:00')->assertCreated();
        $this->seance($ec, 'cm', '10:00', '12:00')->assertCreated();
        $this->seance($ec, 'cm', '14:00', '16:00')->assertStatus(422)
            ->assertJsonFragment(['message' => "L'EC {$ec->code} (EC) a épuisé son volume de CM (4h) : aucune séance de CM supplémentaire ne peut être programmée."]);

        // Le TD garde ses heures.
        $this->seance($ec, 'td', '14:00', '16:00')->assertCreated();
    }

    public function test_td_et_tp_puisent_dans_la_reserve_une_fois_leur_volume_epuise(): void
    {
        $ec = $this->ec(['volume_cm' => 2, 'volume_td' => 2, 'volume_tp' => 0, 'volume_td_tp' => 2]);

        $this->seance($ec, 'td', '08:00', '10:00')->assertCreated();   // volume de TD
        $this->seance($ec, 'td', '10:00', '12:00')->assertCreated();   // réserve TP/TD
        $this->seance($ec, 'tp', '14:00', '15:00')->assertStatus(422)  // réserve épuisée, TP à 0
            ->assertJsonFragment(['message' => "L'EC {$ec->code} (EC) a épuisé son volume de TP (2h) : aucune séance de TP supplémentaire ne peut être programmée."]);

        $restes = collect($this->withToken($this->jeton)->getJson('/api/admin/ecs')->assertOk()->json('data'))->firstWhere('id', $ec->id)['heures_restantes_par_type'];
        $this->assertEquals(['cm' => 2, 'td' => 0, 'tp' => 0], $restes);
    }

    public function test_une_evaluation_ne_consomme_aucun_volume(): void
    {
        $ec = $this->ec(['volume_cm' => 2]);

        $this->seance($ec, 'cm', '08:00', '10:00')->assertCreated();
        $this->seance($ec, 'evaluation', '10:00', '13:00')->assertCreated();
    }

    public function test_un_ec_a_ventiler_garde_son_total_quel_que_soit_le_type(): void
    {
        $ec = $this->ec(['volume_horaire' => 4]);
        $this->assertTrue($ec->volume_a_ventiler);

        $this->seance($ec, 'cm', '08:00', '10:00')->assertCreated();
        $this->seance($ec, 'td', '10:00', '12:00')->assertCreated();
        $this->seance($ec, 'tp', '14:00', '15:00')->assertStatus(422)
            ->assertJsonFragment(['message' => "L'EC {$ec->code} (EC) a épuisé son volume horaire de 4h : aucune séance supplémentaire ne peut être programmée."]);
    }

    public function test_ventiler_un_ec_depuis_le_formulaire(): void
    {
        $ec = $this->ec(['volume_horaire' => 40]);

        $this->withToken($this->jeton)->putJson("/api/admin/ecs/{$ec->id}", ['volume_cm' => 20, 'volume_td_tp' => 15])->assertOk();

        $ec->refresh();
        $this->assertFalse($ec->volume_a_ventiler);
        $this->assertSame(35, $ec->volume_horaire);

        $this->withToken($this->jeton)->postJson('/api/admin/ecs', ['ue_id' => $this->ue->id, 'code' => 'VIDE' . $this->sfx, 'intitule' => 'Sans heures'])
            ->assertStatus(422)->assertJsonValidationErrors('volume_cm');
    }

    public function test_les_imports_recoivent_les_volumes_par_type(): void
    {
        $entete = 'code_ue,intitule_ue,filiere_code,niveau,annee_libelle,semestre,credits_ue,code_ec,intitule_ec,volume_cm,volume_td,volume_tp,volume_td_tp';
        $this->withToken($this->jeton)->postJson('/api/admin/import/csv/courses', ['file' => UploadedFile::fake()->createWithContent('cours.csv',
            "{$entete}\nINF{$this->sfx},Approche objet,{$this->filiere->code},L1,2034-2035,1,6,1INF{$this->sfx},Analyse objet,10,0,0,15\n"
        )])->assertOk()->assertJsonPath('data.success', 1);

        $ec = Ec::where('code', "1INF{$this->sfx}")->firstOrFail();
        $this->assertSame([10, 0, 0, 15, 25, false], [$ec->volume_cm, $ec->volume_td, $ec->volume_tp, $ec->volume_td_tp, $ec->volume_horaire, $ec->volume_a_ventiler]);
        $this->assertSame(6, (int) Ue::where('code', "INF{$this->sfx}")->value('credits'));

        $this->withToken($this->jeton)->postJson('/api/admin/import/validate-courses', ['ues' => [[
            'code' => "IA{$this->sfx}", 'intitule' => 'UE IA', 'filiere_id' => $this->filiere->id, 'annee_id' => $this->annee->id, 'semestre' => 1, 'credits' => 4,
            'ecs' => [['code' => "IA1{$this->sfx}", 'intitule' => 'EC IA', 'volume_cm' => 15, 'volume_td_tp' => 25]],
        ]]])->assertCreated();

        $this->assertSame(40, Ec::where('code', "IA1{$this->sfx}")->value('volume_horaire'));
    }

    public function test_une_seance_generee_prend_le_type_de_son_creneau(): void
    {
        $ec = $this->ec(['volume_cm' => 10, 'volume_td' => 10]);
        EmploiDuTemps::create([
            'ec_id' => $ec->id, 'filiere_id' => $this->filiere->id, 'annee_id' => $this->annee->id,
            'jour_semaine' => today()->addDay()->dayOfWeekIso, 'heure_debut' => '08:00:00', 'heure_fin' => '10:00:00', 'type_cours' => 'td',
        ]);

        $this->ouvrirSemestres($this->annee, $this->filiere->etablissement_id);
        $this->artisan('events:generate-from-schedule', ['--date' => today()->addDay()->toDateString(), '--days' => 1])->assertSuccessful();

        $this->assertSame('td', Evenement::where('ec_id', $ec->id)->value('type_cours'));
    }
}
