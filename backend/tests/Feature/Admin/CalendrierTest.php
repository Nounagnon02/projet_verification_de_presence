<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Fermeture;
use App\Models\Filiere;
use App\Models\PeriodeSemestre;
use App\Models\Presence;
use App\Models\Ue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Calendrier : une séance n'est générée que pendant la période du semestre de
 * son UE, hors fermetures ; sans période déclarée, rien ne l'est.
 */
class CalendrierTest extends TestCase
{
    private string $sfx;
    private Etablissement $etabA;
    private Etablissement $etabB;
    private string $jetonA;
    private string $jetonSuper;
    private AnneeAcademique $annee;
    private Ec $ecImpairA;
    private Ec $ecPairA;
    private Ec $ecB;
    private string $lundi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx = Str::upper(Str::random(5));
        $this->etabA = Etablissement::create(['code' => 'CA' . $this->sfx, 'nom' => 'Faculté A', 'email' => "ca-{$this->sfx}@test.local"]);
        $this->etabB = Etablissement::create(['code' => 'CB' . $this->sfx, 'nom' => 'Faculté B', 'email' => "cb-{$this->sfx}@test.local"]);
        $this->jetonA = User::factory()->faculteAdmin($this->etabA->id)->create(['email' => "cal-a-{$this->sfx}@test.local"])->createToken('t')->plainTextToken;
        $this->jetonSuper = User::factory()->create(['role' => 'super_admin', 'email' => "cal-s-{$this->sfx}@test.local"])->createToken('t')->plainTextToken;

        AnneeAcademique::where('active', true)->update(['active' => false]);
        $this->annee = AnneeAcademique::create(['libelle' => '2044-2045', 'date_debut' => '2025-10-01', 'date_fin' => '2045-09-30', 'active' => true]);
        $this->lundi = Carbon::parse('next monday')->toDateString();

        $filiereA = Filiere::create(['code' => 'FA' . $this->sfx, 'intitule' => 'A (L2)', 'niveau' => 'L2', 'etablissement_id' => $this->etabA->id]);
        $filiereB = Filiere::create(['code' => 'FB' . $this->sfx, 'intitule' => 'B (L2)', 'niveau' => 'L2', 'etablissement_id' => $this->etabB->id]);
        $this->ecImpairA = $this->coursDuLundi($filiereA, 3, '08:00:00');
        $this->ecPairA = $this->coursDuLundi($filiereA, 4, '10:00:00');
        $this->ecB = $this->coursDuLundi($filiereB, 3, '08:00:00');
    }

    /** Un EC du semestre donné, avec un créneau le lundi. */
    private function coursDuLundi(Filiere $filiere, int $semestre, string $debut): Ec
    {
        $ue = Ue::create([
            'code' => "U{$semestre}" . Str::upper(Str::random(6)), 'intitule' => "UE S{$semestre}",
            'filiere_id' => $filiere->id, 'annee_id' => $this->annee->id, 'semestre' => $semestre, 'volume_horaire' => 0,
        ]);
        $ec = Ec::create(['ue_id' => $ue->id, 'code' => 'E' . Str::upper(Str::random(7)), 'intitule' => "EC S{$semestre}", 'volume_cm' => 40]);

        EmploiDuTemps::create([
            'ec_id' => $ec->id, 'filiere_id' => $filiere->id, 'annee_id' => $this->annee->id, 'jour_semaine' => 1,
            'heure_debut' => $debut, 'heure_fin' => Carbon::parse($debut)->addHours(2)->format('H:i:s'), 'salle_libelle' => 'Amphi', 'type_cours' => 'cm',
        ]);

        return $ec;
    }

    private function generer(): PendingCommand
    {
        return $this->artisan('events:generate-from-schedule', ['--date' => $this->lundi, '--days' => 1]);
    }

    private function seances(Ec $ec): int
    {
        return Evenement::where('ec_id', $ec->id)->count();
    }

    private function api(string $jeton, string $methode, string $uri, array $donnees = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($jeton)->json($methode, "/api/{$uri}", $donnees);
    }

    private function fermeture(?Etablissement $etab, string $type, string $libelle, string $du, ?string $au = null): Fermeture
    {
        return Fermeture::create([
            'etablissement_id' => $etab?->id, 'annee_id' => $this->annee->id, 'type' => $type,
            'libelle' => $libelle, 'date_debut' => $du, 'date_fin' => $au ?? $du,
        ]);
    }

    public function test_sans_periode_declaree_rien_n_est_genere_et_la_commande_le_dit(): void
    {
        $this->generer()->expectsOutputToContain('aucune période de semestre déclarée')->assertSuccessful();

        $this->assertSame(0, $this->seances($this->ecImpairA));
    }

    public function test_seul_le_semestre_dont_la_periode_couvre_la_date_est_genere(): void
    {
        $this->ouvrirSemestres($this->annee, $this->etabA->id);
        PeriodeSemestre::where('etablissement_id', $this->etabA->id)->where('parite', 'pair')
            ->update(['date_debut' => '2026-01-05', 'date_fin' => Carbon::parse($this->lundi)->subDay()->toDateString()]);

        $this->generer()->expectsOutputToContain('hors de la période du semestre')->assertSuccessful();

        $this->assertSame(1, $this->seances($this->ecImpairA));
        $this->assertSame(0, $this->seances($this->ecPairA));
    }

    public function test_des_vacances_ne_suspendent_que_leur_faculte_un_jour_ferie_toutes(): void
    {
        $this->ouvrirSemestres($this->annee, $this->etabA->id);
        $this->ouvrirSemestres($this->annee, $this->etabB->id);
        $this->fermeture($this->etabA, 'vacances', 'Vacances de Pâques', $this->lundi);

        $this->generer()->expectsOutputToContain('jour de fermeture')->assertSuccessful();
        $this->assertSame(0, $this->seances($this->ecImpairA));
        $this->assertSame(1, $this->seances($this->ecB), 'Les vacances de A ne touchent pas B.');

        Evenement::where('ec_id', $this->ecB->id)->delete();
        $this->fermeture(null, 'ferie', 'Fête du Vodoun', $this->lundi);

        $this->generer()->assertSuccessful();
        $this->assertSame(0, $this->seances($this->ecB), "Un jour férié de l'université suspend toutes les facultés.");
    }

    /** Seules les séances à venir, jamais ouvertes et sans présence, de la faculté. */
    public function test_declarer_une_fermeture_retire_les_seances_a_venir_qu_elle_couvre(): void
    {
        $retiree = Evenement::factory()->pourEc($this->ecImpairA)->create(['date' => $this->lundi]);
        $avecPresence = Evenement::factory()->pourEc($this->ecImpairA)->create(['date' => $this->lundi, 'heure_debut' => '14:00:00', 'heure_fin' => '16:00:00']);
        Presence::factory()->create([
            'evenement_id' => $avecPresence->id,
            'etudiant_id'  => Etudiant::factory()->pour($this->ecImpairA->ue->filiere, $this->annee),
        ]);
        $autreFaculte = Evenement::factory()->pourEc($this->ecB)->create(['date' => $this->lundi]);
        $apres = Evenement::factory()->pourEc($this->ecImpairA)->create(['date' => Carbon::parse($this->lundi)->addWeek()->toDateString()]);

        $examens = [
            'annee_id' => $this->annee->id, 'type' => 'examens', 'libelle' => 'Examens du S3',
            'date_debut' => $this->lundi, 'date_fin' => Carbon::parse($this->lundi)->addDays(4)->toDateString(),
        ];

        $this->api($this->jetonA, 'POST', 'admin/calendrier/fermetures', $examens + ['apercu' => true])
            ->assertOk()->assertJsonPath('data.seances_a_retirer', 1);
        $this->assertNotSoftDeleted($retiree);

        $this->api($this->jetonA, 'POST', 'admin/calendrier/fermetures', $examens)
            ->assertCreated()->assertJsonPath('data.seances_retirees', 1);

        $this->assertSoftDeleted($retiree);
        foreach ([$avecPresence, $autreFaculte, $apres] as $gardee) {
            $this->assertNotSoftDeleted($gardee);
        }
    }

    public function test_une_faculte_ne_declare_ni_ne_retire_un_jour_ferie(): void
    {
        $this->api($this->jetonA, 'POST', 'admin/calendrier/fermetures', [
            'annee_id' => $this->annee->id, 'type' => 'ferie', 'libelle' => 'Férié', 'date_debut' => $this->lundi,
        ])->assertStatus(422)->assertJsonValidationErrors('type');

        $ferie = $this->fermeture(null, 'ferie', 'Fête du Vodoun', $this->lundi);

        $this->api($this->jetonA, 'DELETE', "admin/calendrier/fermetures/{$ferie->id}")->assertStatus(403);
        $this->assertModelExists($ferie);
    }

    public function test_le_super_admin_declare_un_jour_ferie_pour_toute_l_universite(): void
    {
        // Une date lointaine : aucune autre séance de la base ne tombe ce jour-là.
        $jour = '2044-03-08';
        $a = Evenement::factory()->pourEc($this->ecImpairA)->create(['date' => $jour]);
        $b = Evenement::factory()->pourEc($this->ecB)->create(['date' => $jour]);

        $this->api($this->jetonSuper, 'POST', 'super-admin/jours-feries', ['annee_id' => $this->annee->id, 'libelle' => 'Journée de test', 'date_debut' => $jour])
            ->assertCreated()->assertJsonPath('data.seances_retirees', 2);

        $this->assertSoftDeleted($a);
        $this->assertSoftDeleted($b);
        $this->api($this->jetonSuper, 'GET', "super-admin/jours-feries?annee_id={$this->annee->id}")
            ->assertOk()->assertJsonPath('data.0.libelle', 'Journée de test');
    }

    public function test_une_periode_tient_dans_son_annee_et_ne_chevauche_pas_l_autre_parite(): void
    {
        $periode = fn (string $parite, string $du, string $au) => $this->api($this->jetonA, 'PUT', 'admin/calendrier/periodes', [
            'annee_id' => $this->annee->id, 'parite' => $parite, 'date_debut' => $du, 'date_fin' => $au,
        ]);

        $periode('impair', '2026-10-05', '2027-02-20')->assertOk();
        $periode('pair', '2027-02-01', '2027-07-15')->assertStatus(422)->assertJsonValidationErrors('date_debut');
        $periode('pair', '2027-03-01', '2046-07-15')->assertStatus(422)->assertJsonValidationErrors('date_debut');
        $periode('pair', '2027-03-01', '2027-07-15')->assertOk();
        $periode('impair', '2026-10-12', '2027-02-20')->assertOk();

        $this->assertSame(2, PeriodeSemestre::where('etablissement_id', $this->etabA->id)->count(), 'Modifier une période ne la duplique pas.');
    }

    public function test_le_calendrier_signale_les_semestres_sans_periode(): void
    {
        $this->api($this->jetonA, 'GET', 'admin/calendrier')->assertOk()->assertJsonCount(2, 'data.alertes');

        PeriodeSemestre::create(['etablissement_id' => $this->etabA->id, 'annee_id' => $this->annee->id, 'parite' => 'impair', 'date_debut' => '2026-10-05', 'date_fin' => '2027-02-20']);

        $this->api($this->jetonA, 'GET', 'admin/calendrier')->assertOk()
            ->assertJsonCount(1, 'data.alertes')
            ->assertJsonPath('data.alertes.0.parite', 'pair');
    }
}
