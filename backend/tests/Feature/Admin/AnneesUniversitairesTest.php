<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\Ue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Années académiques communes à l'université, année active par établissement.
 *
 * Le super administrateur crée les années et désigne l'année en cours ; chaque
 * faculté choisit celle sur laquelle elle travaille, sans toucher aux autres.
 * Inscriptions et génération des séances suivent l'année de la faculté.
 */
class AnneesUniversitairesTest extends TestCase
{
    private string $sfx;
    private Etablissement $etabA;
    private Etablissement $etabB;
    private string $jetonA;
    private string $jetonB;
    private string $jetonSuper;

    /** Année en cours de l'université au début de chaque test. */
    private AnneeAcademique $y1;
    private AnneeAcademique $y2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx = Str::lower(Str::random(6));
        $this->etabA = Etablissement::create(['code' => 'YA' . $this->sfx, 'nom' => 'Faculté A', 'email' => "ya-{$this->sfx}@test.local"]);
        $this->etabB = Etablissement::create(['code' => 'YB' . $this->sfx, 'nom' => 'Faculté B', 'email' => "yb-{$this->sfx}@test.local"]);

        $this->jetonA = User::factory()->faculteAdmin($this->etabA->id)->create(['email' => "annees-a-{$this->sfx}@test.local"])->createToken('t')->plainTextToken;
        $this->jetonB = User::factory()->faculteAdmin($this->etabB->id)->create(['email' => "annees-b-{$this->sfx}@test.local"])->createToken('t')->plainTextToken;
        $this->jetonSuper = User::factory()->create(['email' => "annees-s-{$this->sfx}@test.local", 'role' => 'super_admin', 'two_factor_confirmed_at' => now()])->createToken('t')->plainTextToken;

        AnneeAcademique::where('active', true)->update(['active' => false]);
        $this->y1 = $this->annee('2081-2082', true);
        $this->y2 = $this->annee('2082-2083');
    }

    private function annee(string $libelle, bool $active = false): AnneeAcademique
    {
        [$debut, $fin] = explode('-', $libelle);

        return AnneeAcademique::create([
            'libelle' => $libelle, 'date_debut' => "{$debut}-10-01", 'date_fin' => "{$fin}-09-30", 'active' => $active,
        ]);
    }

    /** Le garde d'authentification garde le premier jeton vu : on l'oublie à chaque appel. */
    private function en(string $jeton): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($jeton);
    }

    private function filiere(Etablissement $etab, string $code): Filiere
    {
        return Filiere::create(['code' => $code . $this->sfx, 'intitule' => "Filière {$code}", 'niveau' => 'L1', 'etablissement_id' => $etab->id]);
    }

    private function ec(Filiere $filiere, AnneeAcademique $annee): Ec
    {
        $ue = Ue::factory()->pour($filiere, $annee)->create(['semestre' => 1]);

        return Ec::factory()->create(['ue_id' => $ue->id, 'volume_horaire' => 40]);
    }

    private function creer(array $valeurs): TestResponse
    {
        return $this->en($this->jetonSuper)->postJson('/api/super-admin/annees-academiques', $valeurs);
    }

    // ── Création : réservée au super administrateur ───────────────────────

    public function test_une_faculte_ne_cree_plus_d_annee(): void
    {
        $valeurs = ['libelle' => '2090-2091', 'date_debut' => '2090-10-01', 'date_fin' => '2091-09-30'];

        $this->en($this->jetonA)->postJson('/api/admin/annees-academiques', $valeurs)->assertStatus(405);
        $this->en($this->jetonA)->postJson('/api/super-admin/annees-academiques', $valeurs)->assertStatus(403);

        $this->assertDatabaseMissing('annees_academiques', ['libelle' => '2090-2091']);
    }

    /**
     * En « datetime », le 1er octobre à minuit sortait « 30 septembre 23 h UTC » :
     * renvoyer au serveur ce qu'il avait donné faisait reculer l'année d'un jour.
     */
    public function test_les_dates_restent_des_dates_d_un_enregistrement_a_l_autre(): void
    {
        $cree = $this->creer(['libelle' => '2090-2091', 'date_debut' => '2090-10-01', 'date_fin' => '2091-09-30'])
            ->assertCreated()
            ->assertJsonPath('data.date_debut', '2090-10-01')
            ->assertJsonPath('data.date_fin', '2091-09-30');

        $id = $cree->json('data.id');
        $lue = collect($this->en($this->jetonSuper)->getJson('/api/super-admin/annees-academiques')->json('data'))->firstWhere('id', $id);

        $this->en($this->jetonSuper)
            ->putJson("/api/super-admin/annees-academiques/{$id}", [
                'libelle' => $lue['libelle'], 'date_debut' => $lue['date_debut'], 'date_fin' => $lue['date_fin'],
            ])->assertOk();

        $annee = AnneeAcademique::find($id);
        $this->assertSame('2090-10-01', $annee->date_debut->toDateString());
        $this->assertSame('2091-09-30', $annee->date_fin->toDateString());
    }

    public function test_le_libelle_et_les_dates_se_repondent(): void
    {
        $this->creer(['libelle' => 'Année 2090', 'date_debut' => '2090-10-01', 'date_fin' => '2091-09-30'])
            ->assertStatus(422)->assertJsonValidationErrors('libelle');

        $this->creer(['libelle' => '2090-2092', 'date_debut' => '2090-10-01', 'date_fin' => '2091-09-30'])
            ->assertStatus(422)->assertJsonValidationErrors('libelle');

        $this->creer(['libelle' => '2090-2091', 'date_debut' => '2089-10-01', 'date_fin' => '2091-09-30'])
            ->assertStatus(422)->assertJsonValidationErrors('date_debut');

        $this->creer(['libelle' => '2090-2091', 'date_debut' => '2090-10-01', 'date_fin' => '2092-01-31'])
            ->assertStatus(422)->assertJsonValidationErrors('date_fin');

        $this->creer(['libelle' => '2081-2082', 'date_debut' => '2081-10-01', 'date_fin' => '2082-09-30'])
            ->assertStatus(422)->assertJsonValidationErrors('libelle');
    }

    public function test_deux_annees_ne_se_chevauchent_pas(): void
    {
        // 2082-2083 court jusqu'au 30 septembre 2083.
        $this->creer(['libelle' => '2083-2084', 'date_debut' => '2083-09-15', 'date_fin' => '2084-09-30'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('date_debut');

        $this->creer(['libelle' => '2083-2084', 'date_debut' => '2083-10-01', 'date_fin' => '2084-09-30'])
            ->assertCreated();
    }

    // ── Bascule : par établissement ───────────────────────────────────────

    /**
     * On désactivait toutes les autres années : la bascule d'une faculté
     * changeait l'année de toutes les autres.
     */
    public function test_chaque_faculte_bascule_sans_toucher_aux_autres(): void
    {
        $this->en($this->jetonA)->patchJson("/api/admin/annees-academiques/{$this->y2->id}/activate")->assertOk();

        $this->assertSame($this->y2->id, AnneeAcademique::activePour($this->etabA->id)->id);
        $this->assertSame($this->y1->id, AnneeAcademique::activePour($this->etabB->id)->id);
        $this->assertTrue($this->y1->fresh()->active, "L'année en cours de l'université ne bouge pas.");

        $vueA = collect($this->en($this->jetonA)->getJson('/api/admin/annees-academiques')->assertOk()->json('data'))->keyBy('id');
        $this->assertTrue($vueA[$this->y2->id]['active']);
        $this->assertFalse($vueA[$this->y1->id]['active']);
        $this->assertTrue($vueA[$this->y1->id]['en_cours_universite']);

        $vueB = collect($this->en($this->jetonB)->getJson('/api/admin/annees-academiques')->assertOk()->json('data'))->keyBy('id');
        $this->assertTrue($vueB[$this->y1->id]['active']);
        $this->assertFalse($vueB[$this->y2->id]['active']);
    }

    public function test_le_super_admin_ne_bascule_pas_les_facultes_par_leur_route(): void
    {
        $this->en($this->jetonSuper)->patchJson("/api/admin/annees-academiques/{$this->y2->id}/activate")->assertStatus(422);

        $this->assertTrue($this->y1->fresh()->active);
        $this->assertFalse($this->y2->fresh()->active);
    }

    public function test_changer_l_annee_en_cours_respecte_le_choix_des_facultes(): void
    {
        // A a choisi 2081-2082 ; B suit l'université.
        $this->etabA->update(['annee_active_id' => $this->y1->id]);

        $this->en($this->jetonSuper)->patchJson("/api/super-admin/annees-academiques/{$this->y2->id}/en-cours")->assertOk();

        $this->assertFalse($this->y1->fresh()->active);
        $this->assertTrue($this->y2->fresh()->active);
        $this->assertSame($this->y1->id, AnneeAcademique::activePour($this->etabA->id)->id);
        $this->assertSame($this->y2->id, AnneeAcademique::activePour($this->etabB->id)->id);

        $ligne = collect($this->en($this->jetonSuper)->getJson('/api/super-admin/annees-academiques')->json('data'))->firstWhere('id', $this->y1->id);
        $this->assertSame([['code' => $this->etabA->code, 'nom' => 'Faculté A', 'suit_universite' => false]], $ligne['etablissements']);
    }

    /**
     * Les séances déjà générées pour l'année quittée seraient clôturées sans
     * personne, et chaque étudiant y serait compté absent. On retire celles à
     * venir, jamais ouvertes et sans présence — et seulement chez la faculté
     * qui bascule.
     */
    public function test_basculer_retire_les_seances_deja_planifiees_de_l_annee_quittee(): void
    {
        $filiereA = $this->filiere($this->etabA, 'SA');
        $ecA = $this->ec($filiereA, $this->y1);
        $filiereB = $this->filiere($this->etabB, 'SB');
        $ecB = $this->ec($filiereB, $this->y1);

        $demain = today()->addDay()->toDateString();
        $aRetirer = Evenement::factory()->pourEc($ecA)->create(['date' => $demain]);
        $avecPresence = Evenement::factory()->pourEc($ecA)->create(['date' => $demain, 'heure_debut' => '10:00:00', 'heure_fin' => '12:00:00']);
        Presence::factory()->create([
            'evenement_id' => $avecPresence->id,
            'etudiant_id'  => Etudiant::factory()->pour($filiereA, $this->y1)->create()->id,
        ]);
        $aujourdhui = Evenement::factory()->pourEc($ecA)->create(['date' => today()->toDateString()]);
        $autreFaculte = Evenement::factory()->pourEc($ecB)->create(['date' => $demain]);

        $annonce = collect($this->en($this->jetonA)->getJson('/api/admin/annees-academiques')->json('data'))->firstWhere('id', $this->y1->id);
        $this->assertSame(1, $annonce['seances_a_venir_count'], 'La confirmation annonce ce qui sera retiré.');

        $this->en($this->jetonA)->patchJson("/api/admin/annees-academiques/{$this->y2->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.seances_retirees', 1);

        $this->assertSoftDeleted($aRetirer);
        $this->assertNotSoftDeleted($avecPresence);
        $this->assertNotSoftDeleted($aujourdhui);
        $this->assertNotSoftDeleted($autreFaculte);
    }

    // ── Suppression : jamais une année qui contient quelque chose ─────────

    public function test_on_ne_supprime_ni_l_annee_en_cours_ni_celle_d_une_faculte(): void
    {
        $this->en($this->jetonSuper)->deleteJson("/api/super-admin/annees-academiques/{$this->y1->id}")->assertStatus(409);

        $this->etabA->update(['annee_active_id' => $this->y2->id]);
        $this->en($this->jetonSuper)->deleteJson("/api/super-admin/annees-academiques/{$this->y2->id}")
            ->assertStatus(409)
            ->assertJsonFragment(['message' => "2082-2083 est l'année active de : {$this->etabA->code}."]);

        $this->assertNotNull($this->y1->fresh());
        $this->assertNotNull($this->y2->fresh());
    }

    /**
     * Les tables liées sont en cascade. Le contrôle ne regardait que les
     * étudiants et les séances : une année préparée, avec sa maquette mais sans
     * étudiant, disparaissait entière.
     */
    public function test_une_annee_preparee_mais_sans_etudiant_ne_se_supprime_pas(): void
    {
        $preparee = $this->annee('2084-2085');
        $this->ec($this->filiere($this->etabA, 'PR'), $preparee);

        $reponse = $this->en($this->jetonSuper)->deleteJson("/api/super-admin/annees-academiques/{$preparee->id}")->assertStatus(409);
        $this->assertStringContainsString('1 UE', $reponse->json('message'));
        $this->assertNotNull($preparee->fresh());

        $vide = $this->annee('2085-2086');
        $this->en($this->jetonSuper)->deleteJson("/api/super-admin/annees-academiques/{$vide->id}")->assertOk();
        $this->assertNull($vide->fresh());
    }

    // ── Ce qui suit l'année de la faculté ─────────────────────────────────

    public function test_l_inscription_se_fait_dans_l_annee_de_la_faculte(): void
    {
        $this->etabA->update(['annee_active_id' => $this->y2->id]);
        $filiere = $this->filiere($this->etabA, 'IN');

        $reponse = $this->en($this->jetonA)->postJson('/api/admin/students', [
            'nom' => 'ANNEE', 'prenom' => 'Faculté', 'matricule' => 'Y-' . $this->sfx,
            'filiere_id' => $filiere->id, 'email' => "inscrit-{$this->sfx}@test.local",
        ])->assertCreated();

        $this->assertSame($this->y2->id, Etudiant::findOrFail($reponse->json('data.id'))->annee_id);
    }

    /**
     * La génération prenait tous les créneaux, toutes années confondues : après
     * la fin d'une année, ses cours continuaient d'être générés, en même temps
     * que ceux de la suivante.
     */
    public function test_la_generation_ne_suit_que_l_annee_active_de_chaque_faculte(): void
    {
        $this->etabA->update(['annee_active_id' => $this->y2->id]);

        $filiereA = $this->filiere($this->etabA, 'GA');
        $ancienA = $this->ec($filiereA, $this->y1);
        $nouveauA = $this->ec($filiereA, $this->y2);
        $filiereB = $this->filiere($this->etabB, 'GB');
        $courantB = $this->ec($filiereB, $this->y1);

        foreach ([[$ancienA, $this->y1, '08:00:00'], [$nouveauA, $this->y2, '10:00:00'], [$courantB, $this->y1, '14:00:00']] as [$ec, $annee, $debut]) {
            EmploiDuTemps::create([
                'ec_id' => $ec->id, 'filiere_id' => $ec->ue->filiere_id, 'annee_id' => $annee->id,
                'jour_semaine' => 1, 'heure_debut' => $debut, 'heure_fin' => Carbon::parse($debut)->addHours(2)->format('H:i:s'),
                'salle_libelle' => 'Amphi', 'type_cours' => 'cours',
            ]);
        }

        foreach ([$this->etabA, $this->etabB] as $etab) {
            foreach ([$this->y1, $this->y2] as $annee) {
                $this->ouvrirSemestres($annee, $etab->id);
            }
        }

        $lundi = Carbon::parse('next monday')->toDateString();
        $this->artisan('events:generate-from-schedule', ['--date' => $lundi, '--days' => 1])->assertSuccessful();

        $this->assertSame(0, Evenement::where('ec_id', $ancienA->id)->count(), "L'ancienne année de A n'est plus générée.");
        $this->assertSame(1, Evenement::where('ec_id', $nouveauA->id)->count(), 'La nouvelle année de A est générée.');
        $this->assertSame(1, Evenement::where('ec_id', $courantB->id)->count(), "B, qui suit l'université, reste sur 2081-2082.");
    }
}
