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
use App\Services\AttendanceRateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Une promotion ne réécrit pas l'année quittée.
 *
 * Les taux joignent etudiant_ec sur l'année. La promotion détachait toutes les
 * inscriptions de l'étudiant, puis autoEnroll() recalait les restantes sur la
 * nouvelle année : l'année quittée gardait ses présences mais perdait ses
 * attendus, et son taux devenait faux.
 */
class PromotionHistoriqueTest extends TestCase
{
    private string $jeton;
    private AnneeAcademique $a;
    private AnneeAcademique $b;
    private Filiere $l1;
    private Filiere $l2;
    private Ec $ecA;
    private Ec $ecB;
    private Etudiant $etudiant;

    protected function setUp(): void
    {
        parent::setUp();

        $sfx = Str::lower(Str::random(6));
        $etab = Etablissement::create(['code' => 'PH' . $sfx, 'nom' => 'Faculté H', 'email' => "ph-{$sfx}@test.local"]);
        $this->jeton = User::factory()->faculteAdmin($etab->id)->create(['email' => "promo-{$sfx}@test.local"])->createToken('t')->plainTextToken;

        AnneeAcademique::where('active', true)->update(['active' => false]);
        $this->a = AnneeAcademique::create(['libelle' => '2071-2072', 'date_debut' => '2071-10-01', 'date_fin' => '2072-09-30', 'active' => true]);
        $this->b = AnneeAcademique::create(['libelle' => '2072-2073', 'date_debut' => '2072-10-01', 'date_fin' => '2073-09-30']);

        $this->l1 = Filiere::create(['code' => 'H1' . $sfx, 'intitule' => 'H (L1)', 'niveau' => 'L1', 'etablissement_id' => $etab->id]);
        $this->l2 = Filiere::create(['code' => 'H2' . $sfx, 'intitule' => 'H (L2)', 'niveau' => 'L2', 'etablissement_id' => $etab->id]);

        $this->ecA = Ec::factory()->create(['ue_id' => Ue::factory()->pour($this->l1, $this->a)->create(['semestre' => 1])->id]);
        $this->ecB = Ec::factory()->create(['ue_id' => Ue::factory()->pour($this->l2, $this->b)->create(['semestre' => 3])->id]);

        $this->etudiant = Etudiant::factory()->pour($this->l1, $this->a)->create();
        $this->etudiant->autoEnroll();

        $seance = Evenement::factory()->pourEc($this->ecA)->passe()->create();
        Presence::factory()->create(['etudiant_id' => $this->etudiant->id, 'evenement_id' => $seance->id]);
    }

    /** @return array<int, array{int, int}> paires [ec_id, annee_id] */
    private function inscriptions(): array
    {
        return DB::table('etudiant_ec')->where('etudiant_id', $this->etudiant->id)
            ->orderBy('annee_id')->get(['ec_id', 'annee_id'])
            ->map(fn ($l) => [(int) $l->ec_id, (int) $l->annee_id])->all();
    }

    private function attendusDe(AnneeAcademique $annee): int
    {
        return app(AttendanceRateService::class)->expected(fn ($q) => $q->where('e.annee_id', $annee->id));
    }

    private function promouvoir(): void
    {
        $this->withToken($this->jeton)->postJson('/api/admin/students/promote', [
            'from_filiere_id' => $this->l1->id, 'to_filiere_id' => $this->l2->id, 'to_annee_id' => $this->b->id,
        ])->assertOk();
    }

    public function test_la_promotion_garde_les_inscriptions_et_le_taux_de_l_annee_quittee(): void
    {
        $this->assertSame(1, $this->attendusDe($this->a));

        $this->promouvoir();

        $this->assertSame([[$this->ecA->id, $this->a->id], [$this->ecB->id, $this->b->id]], $this->inscriptions());
        $this->assertSame(1, $this->attendusDe($this->a), "L'année quittée garde ses présences attendues.");
    }

    public function test_reinscrire_ne_touche_que_l_annee_courante(): void
    {
        $this->promouvoir();

        $this->withToken($this->jeton)->postJson("/api/admin/students/{$this->etudiant->id}/ecs/reset")->assertOk();

        $this->assertSame([[$this->ecA->id, $this->a->id], [$this->ecB->id, $this->b->id]], $this->inscriptions());
    }

    /** Changer l'année dans la fiche corrige une erreur : l'année erronée ne garde rien. */
    public function test_corriger_l_annee_d_un_etudiant_efface_celle_qui_etait_fausse(): void
    {
        $this->withToken($this->jeton)->putJson("/api/admin/students/{$this->etudiant->id}", [
            'filiere_id' => $this->l2->id, 'annee_id' => $this->b->id,
        ])->assertOk();

        $this->assertSame([[$this->ecB->id, $this->b->id]], $this->inscriptions());
    }

    public function test_une_annee_ne_parait_pas_vide_apres_la_promotion_de_ses_etudiants(): void
    {
        $this->promouvoir();

        $annees = collect($this->withToken($this->jeton)->getJson('/api/admin/annees-academiques')->assertOk()->json('data'))->keyBy('id');

        $this->assertSame(1, $annees[$this->a->id]['etudiants_count']);
        $this->assertSame(1, $annees[$this->b->id]['etudiants_count']);
    }
}
