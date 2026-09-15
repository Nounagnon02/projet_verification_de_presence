<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Etablissement;
use App\Models\Filiere;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Préparer l'année suivante : filières, maquette et emploi du temps copiés
 * depuis l'année active, sans doublon, dans le périmètre de l'établissement.
 */
class PreparationAnneeTest extends TestCase
{
    private string $sfx;
    private string $jetonA;
    private AnneeAcademique $source;
    private AnneeAcademique $cible;
    private Filiere $f1;
    private Filiere $f2;
    private Filiere $fb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx = Str::lower(Str::random(6));
        $etabA = Etablissement::create(['code' => 'PA' . $this->sfx, 'nom' => 'Faculté A', 'email' => "pa-{$this->sfx}@test.local"]);
        $etabB = Etablissement::create(['code' => 'PB' . $this->sfx, 'nom' => 'Faculté B', 'email' => "pb-{$this->sfx}@test.local"]);
        $this->jetonA = User::factory()->faculteAdmin($etabA->id)->create(['email' => "prep-{$this->sfx}@test.local"])->createToken('t')->plainTextToken;

        AnneeAcademique::where('active', true)->update(['active' => false]);
        $this->source = AnneeAcademique::create(['libelle' => '2061-2062', 'date_debut' => '2061-10-01', 'date_fin' => '2062-09-30', 'active' => true]);
        $this->cible = AnneeAcademique::create(['libelle' => '2062-2063', 'date_debut' => '2062-10-01', 'date_fin' => '2063-09-30']);

        // F1 : 2 UE de 2 EC, 2 créneaux. F2 : a déjà sa maquette dans la cible. FB : une autre faculté.
        $this->f1 = $this->filiere($etabA, 'F1');
        foreach (['UE1', 'UE2'] as $i => $code) {
            $ue = Ue::create(['code' => $code, 'intitule' => "UE {$code}", 'filiere_id' => $this->f1->id, 'annee_id' => $this->source->id, 'semestre' => 1, 'volume_horaire' => 40]);
            foreach (['A', 'B'] as $lettre) {
                $ec = Ec::create(['ue_id' => $ue->id, 'code' => "{$code}-{$lettre}", 'intitule' => "EC {$lettre}", 'volume_horaire' => 20]);
                if ($lettre === 'A') {
                    $this->creneau($ec, $this->f1, $this->source, $i + 1);
                }
            }
        }

        $this->f2 = $this->filiere($etabA, 'F2');
        Ue::create(['code' => 'OLD', 'intitule' => 'Ancienne', 'filiere_id' => $this->f2->id, 'annee_id' => $this->source->id, 'semestre' => 1, 'volume_horaire' => 20]);
        Ue::create(['code' => 'NEW', 'intitule' => 'Nouvelle', 'filiere_id' => $this->f2->id, 'annee_id' => $this->cible->id, 'semestre' => 1, 'volume_horaire' => 20]);

        $this->fb = $this->filiere($etabB, 'FB');
        Ue::create(['code' => 'UB', 'intitule' => 'Autre', 'filiere_id' => $this->fb->id, 'annee_id' => $this->source->id, 'semestre' => 1, 'volume_horaire' => 20]);
    }

    private function filiere(Etablissement $etab, string $code): Filiere
    {
        return Filiere::create(['code' => $code . $this->sfx, 'intitule' => "Filière {$code}", 'niveau' => 'L1', 'etablissement_id' => $etab->id]);
    }

    private function creneau(Ec $ec, Filiere $filiere, AnneeAcademique $annee, int $jour): void
    {
        EmploiDuTemps::create([
            'ec_id' => $ec->id, 'filiere_id' => $filiere->id, 'annee_id' => $annee->id, 'jour_semaine' => $jour,
            'heure_debut' => '08:00:00', 'heure_fin' => '10:00:00', 'salle_libelle' => 'Amphi', 'type_cours' => 'cours',
        ]);
    }

    private function preparer(array $filieres, bool $avecEdt = true, ?string $jeton = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($jeton ?? $this->jetonA)->postJson("/api/admin/annees-academiques/{$this->cible->id}/preparer", [
            'filiere_ids' => array_map(fn (Filiere $f) => $f->id, $filieres), 'avec_edt' => $avecEdt,
        ]);
    }

    public function test_l_apercu_dit_ce_qui_sera_copie_et_ce_qui_existe_deja(): void
    {
        $filieres = collect($this->withToken($this->jetonA)
            ->getJson("/api/admin/annees-academiques/{$this->cible->id}/preparation")
            ->assertOk()
            ->assertJsonPath('data.source.libelle', '2061-2062')
            ->json('data.filieres'))->keyBy('id');

        $this->assertEqualsCanonicalizing([$this->f1->id, $this->f2->id], $filieres->keys()->all(), "Seules les filières de l'établissement.");
        $this->assertSame(['ues' => 2, 'ecs' => 4, 'creneaux' => 2], $filieres[$this->f1->id]['source']);
        $this->assertFalse($filieres[$this->f1->id]['deja_preparee']);
        $this->assertTrue($filieres[$this->f2->id]['deja_preparee']);
    }

    public function test_la_maquette_et_l_emploi_du_temps_sont_copies_sur_les_nouveaux_ec(): void
    {
        $this->preparer([$this->f1])->assertOk()->assertJsonPath('data.filieres.0.ues', 2)->assertJsonPath('data.filieres.0.ecs', 4)->assertJsonPath('data.filieres.0.creneaux', 2);

        $ues = Ue::where('filiere_id', $this->f1->id)->where('annee_id', $this->cible->id)->with('ecs')->get();
        $this->assertSame(['UE1', 'UE2'], $ues->pluck('code')->sort()->values()->all());
        $this->assertSame(['non_demarre'], $ues->pluck('statut')->unique()->values()->all(), 'Une année neuve démarre à zéro.');
        $this->assertSame(['non_demarre'], $ues->flatMap->ecs->pluck('statut')->unique()->values()->all());

        $ecsCible = $ues->flatMap->ecs->pluck('id')->all();
        $creneaux = EmploiDuTemps::where('filiere_id', $this->f1->id)->where('annee_id', $this->cible->id)->get();
        $this->assertCount(2, $creneaux);
        $this->assertEmpty(array_diff($creneaux->pluck('ec_id')->all(), $ecsCible), 'Les créneaux pointent les EC de la cible.');

        $this->assertTrue($this->f1->anneesAcademiques()->where('annee_id', $this->cible->id)->exists());
    }

    public function test_relancer_ne_cree_aucun_doublon(): void
    {
        $this->preparer([$this->f1])->assertOk();
        $this->preparer([$this->f1])->assertOk()->assertJsonPath('data.filieres.0.maquette_gardee', true)->assertJsonPath('data.filieres.0.edt_garde', true);

        $this->assertSame(2, Ue::where('filiere_id', $this->f1->id)->where('annee_id', $this->cible->id)->count());
        $this->assertSame(2, EmploiDuTemps::where('filiere_id', $this->f1->id)->where('annee_id', $this->cible->id)->count());
    }

    public function test_une_filiere_deja_preparee_garde_sa_maquette(): void
    {
        $this->preparer([$this->f2])->assertOk()->assertJsonPath('data.filieres.0.maquette_gardee', true);

        $this->assertSame(['NEW'], Ue::where('filiere_id', $this->f2->id)->where('annee_id', $this->cible->id)->pluck('code')->all());
    }

    public function test_l_emploi_du_temps_se_complete_apres_coup(): void
    {
        $this->preparer([$this->f1], avecEdt: false)->assertOk()->assertJsonPath('data.filieres.0.creneaux', 0);
        $this->preparer([$this->f1])->assertOk()->assertJsonPath('data.filieres.0.maquette_gardee', true)->assertJsonPath('data.filieres.0.creneaux', 2);
    }

    public function test_hors_perimetre_rien_n_est_copie(): void
    {
        $this->preparer([$this->fb])->assertStatus(422);
        $this->assertSame(0, Ue::where('filiere_id', $this->fb->id)->where('annee_id', $this->cible->id)->count());

        $super = User::factory()->create(['email' => "prep-s-{$this->sfx}@test.local", 'role' => 'super_admin'])->createToken('t')->plainTextToken;
        $this->preparer([$this->f1], jeton: $super)->assertStatus(422);

        // On ne prépare pas une année passée à partir d'une plus récente.
        $this->app['auth']->forgetGuards();
        $this->withToken($this->jetonA)->postJson("/api/admin/annees-academiques/{$this->source->id}/preparer", [
            'source_annee_id' => $this->cible->id, 'filiere_ids' => [$this->f1->id],
        ])->assertStatus(422);
    }
}
