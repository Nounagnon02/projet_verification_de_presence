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
use App\Services\AttendanceRateService;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * La maquette réelle de l'IFRI, importée quand les étudiants étaient déjà
 * inscrits, ne les inscrivait à aucun de ses cours : les séances de ces cours
 * n'attendaient personne, et le scan les refusait.
 */
class InscriptionAuxNouveauxCoursTest extends TestCase
{
    private string $jeton;
    private AnneeAcademique $annee;
    private Filiere $im;
    private Filiere $gl;

    protected function setUp(): void
    {
        parent::setUp();

        $sfx = Str::upper(Str::random(5));
        $etab = Etablissement::create(['code' => 'NC' . $sfx, 'nom' => 'Faculté N', 'email' => "nc-{$sfx}@test.local"]);
        $this->jeton = User::factory()->faculteAdmin($etab->id)->create(['email' => "nouveaux-{$sfx}@test.local"])->createToken('t')->plainTextToken;

        AnneeAcademique::where('active', true)->update(['active' => false]);
        $this->annee = AnneeAcademique::create(['libelle' => '2052-2053', 'date_debut' => '2025-10-01', 'date_fin' => '2053-09-30', 'active' => true]);
        $this->im = Filiere::create(['code' => 'IM' . $sfx, 'intitule' => 'IM (L2)', 'niveau' => 'L2', 'etablissement_id' => $etab->id]);
        $this->gl = Filiere::create(['code' => 'GL' . $sfx, 'intitule' => 'GL (L2)', 'niveau' => 'L2', 'etablissement_id' => $etab->id]);

        foreach ([[$this->im, 3], [$this->gl, 2]] as [$filiere, $nombre]) {
            Etudiant::factory()->count($nombre)->pour($filiere, $this->annee)->create();
        }
    }

    private function attendus(Ec $ec): int
    {
        $seance = Evenement::factory()->pourEc($ec)->create(['date' => today()->addWeek()->toDateString()]);

        return app(AttendanceRateService::class)->expected(fn ($q) => $q->where('e.id', $seance->id));
    }

    public function test_une_maquette_importee_apres_les_inscriptions_attend_la_promotion(): void
    {
        $this->withToken($this->jeton)->postJson('/api/admin/import/validate-courses', ['ues' => [[
            'code' => 'MTH1321', 'intitule' => 'Structures algébriques', 'filiere_id' => $this->im->id,
            'annee_id' => $this->annee->id, 'semestre' => 3, 'credits' => 5,
            'ecs' => [['code' => '1MTH1321', 'intitule' => 'Structures algébriques', 'volume_cm' => 30, 'volume_td_tp' => 20]],
        ]]])->assertCreated();

        $this->assertSame(3, $this->attendus(Ec::where('code', '1MTH1321')->where('annee_id', $this->annee->id)->firstOrFail()));
    }

    public function test_un_ec_qui_change_d_ue_suit_le_public_de_sa_nouvelle_ue(): void
    {
        $ueIm = Ue::create(['code' => 'UIM' . Str::random(4), 'intitule' => 'UE IM', 'filiere_id' => $this->im->id, 'annee_id' => $this->annee->id, 'semestre' => 3, 'volume_horaire' => 0]);
        $ueGl = Ue::create(['code' => 'UGL' . Str::random(4), 'intitule' => 'UE GL', 'filiere_id' => $this->gl->id, 'annee_id' => $this->annee->id, 'semestre' => 3, 'volume_horaire' => 0]);
        $ec = Ec::create(['ue_id' => $ueIm->id, 'code' => 'EC' . Str::random(5), 'intitule' => 'EC', 'volume_cm' => 10]);

        $this->assertSame(3, $this->attendus($ec));

        $ec->update(['ue_id' => $ueGl->id]);

        $this->assertSame(2, $this->attendus($ec->fresh()), 'Les étudiants d\'IM ne sont plus attendus à un cours de GL.');
    }
}
