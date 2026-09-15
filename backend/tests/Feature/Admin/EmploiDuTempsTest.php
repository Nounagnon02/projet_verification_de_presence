<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Etablissement;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Groupe;
use App\Models\Salle;
use App\Models\Ue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * L'emploi du temps comme objet, et une seule règle de conflit.
 *
 * Deux occupations du même moment se gênent si elles prennent la même salle ou
 * le même public : toutes les filières d'un cours commun, un groupe seul pour
 * une séance de groupe. Deux groupes différents ne se gênent pas. Les années,
 * et les versions d'un emploi du temps qui ne se recouvrent pas, non plus.
 */
class EmploiDuTempsTest extends TestCase
{
    private string $sfx;
    private string $jeton;
    private Etablissement $etab;
    private AnneeAcademique $annee;
    private Filiere $im;
    private Filiere $gl;
    private Ue $ueCommune;
    private Ec $commun;
    private Ec $propreIm;
    private Salle $s1;
    private Salle $s2;
    private Groupe $g1;
    private Groupe $g2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx = Str::upper(Str::random(5));
        $this->etab = Etablissement::create(['code' => 'ED' . $this->sfx, 'nom' => 'Faculté E', 'email' => "ed-{$this->sfx}@test.local"]);
        $this->jeton = User::factory()->faculteAdmin($this->etab->id)->create(['email' => "edt-{$this->sfx}@test.local"])->createToken('t')->plainTextToken;

        AnneeAcademique::where('active', true)->update(['active' => false]);
        $this->annee = AnneeAcademique::create(['libelle' => '2046-2047', 'date_debut' => '2025-10-01', 'date_fin' => '2047-09-30', 'active' => true]);

        $this->im = Filiere::create(['code' => 'IM' . $this->sfx, 'intitule' => 'IM (L2)', 'niveau' => 'L2', 'etablissement_id' => $this->etab->id]);
        $this->gl = Filiere::create(['code' => 'GL' . $this->sfx, 'intitule' => 'GL (L2)', 'niveau' => 'L2', 'etablissement_id' => $this->etab->id]);

        // Un cours commun à IM et GL, et un cours d'IM seule.
        $this->ueCommune = Ue::create(['code' => 'UC' . $this->sfx, 'intitule' => 'Cours commun', 'filiere_id' => $this->im->id, 'annee_id' => $this->annee->id, 'semestre' => 3, 'volume_horaire' => 0]);
        $this->ueCommune->filieres()->syncWithoutDetaching([$this->gl->id]);
        $this->commun = Ec::create(['ue_id' => $this->ueCommune->id, 'code' => 'EC' . $this->sfx, 'intitule' => 'Commun', 'volume_cm' => 40, 'volume_td' => 20]);
        $ueIm = Ue::create(['code' => 'UI' . $this->sfx, 'intitule' => 'Cours IM', 'filiere_id' => $this->im->id, 'annee_id' => $this->annee->id, 'semestre' => 3, 'volume_horaire' => 0]);
        $this->propreIm = Ec::create(['ue_id' => $ueIm->id, 'code' => 'EI' . $this->sfx, 'intitule' => 'Propre à IM', 'volume_cm' => 40]);

        $this->s1 = Salle::factory()->create(['etablissement_id' => $this->etab->id, 'nom' => "Amphi A {$this->sfx}", 'code' => "AA{$this->sfx}"]);
        $this->s2 = Salle::factory()->create(['etablissement_id' => $this->etab->id, 'nom' => "Salle TD {$this->sfx}", 'code' => "TD{$this->sfx}"]);

        $this->g1 = Groupe::create(['filiere_id' => $this->im->id, 'annee_id' => $this->annee->id, 'type' => 'td', 'libelle' => 'G1']);
        $this->g2 = Groupe::create(['filiere_id' => $this->im->id, 'annee_id' => $this->annee->id, 'type' => 'td', 'libelle' => 'G2']);
    }

    private function api(string $methode, string $uri, array $donnees = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->jeton)->json($methode, "/api/admin/{$uri}", $donnees);
    }

    private function creneau(Ec $ec, array $valeurs = []): TestResponse
    {
        return $this->api('POST', 'emploi-du-temps', $valeurs + [
            'ec_id' => $ec->id, 'jour_semaine' => 1, 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'type_cours' => 'cm',
        ]);
    }

    private function lundiProchain(): string
    {
        return Carbon::parse('next monday')->toDateString();
    }

    public function test_un_creneau_se_cree_se_modifie_et_se_supprime(): void
    {
        $id = $this->creneau($this->commun, ['salle_id' => $this->s1->id, 'enseignant' => 'M. HOUNDJI'])->assertCreated()
            ->assertJsonPath('data.salle', $this->s1->nom)
            ->assertJsonPath('data.enseignant', 'HOUNDJI')
            ->json('data.id');

        // Un cours commun s'affiche aussi dans l'emploi du temps de GL.
        $this->api('GET', "emploi-du-temps?filiere_id={$this->gl->id}")->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.filieres', [$this->im->code, $this->gl->code]);

        $this->api('PUT', "emploi-du-temps/{$id}", [
            'ec_id' => $this->commun->id, 'jour_semaine' => 2, 'heure_debut' => '10:00', 'heure_fin' => '12:00', 'type_cours' => 'cm',
        ])->assertOk()->assertJsonPath('data.jour_semaine', 2);

        $this->api('DELETE', "emploi-du-temps/{$id}")->assertOk();
        $this->assertSame(0, EmploiDuTemps::where('annee_id', $this->annee->id)->count());
    }

    public function test_un_cours_commun_occupe_toutes_ses_filieres(): void
    {
        $this->creneau($this->commun)->assertCreated();

        $this->creneau($this->propreIm, ['heure_debut' => '09:00', 'heure_fin' => '11:00'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => "Conflit de promotion : {$this->im->code} a déjà {$this->commun->code} Lundi de 08:00 à 10:00."]);
    }

    public function test_deux_groupes_se_partagent_un_moment_que_la_promotion_ne_partage_pas(): void
    {
        $this->creneau($this->commun, ['type_cours' => 'td', 'groupe_id' => $this->g1->id, 'salle_id' => $this->s1->id])->assertCreated();
        $this->creneau($this->commun, ['type_cours' => 'td', 'groupe_id' => $this->g2->id, 'salle_id' => $this->s2->id])->assertCreated();

        // Toute la promotion d'IM, au moment où ses groupes sont en TD.
        $this->creneau($this->propreIm, ['heure_debut' => '09:00', 'heure_fin' => '10:00'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => "Conflit de promotion : {$this->im->code} a déjà {$this->commun->code} (groupe G1) Lundi de 08:00 à 10:00. Conflit de promotion : {$this->im->code} a déjà {$this->commun->code} (groupe G2) Lundi de 08:00 à 10:00."]);
    }

    public function test_ni_une_autre_annee_ni_une_autre_version_ne_genent(): void
    {
        $autreAnnee = AnneeAcademique::create(['libelle' => '2048-2049', 'date_debut' => '2048-10-01', 'date_fin' => '2049-09-30', 'active' => false]);
        EmploiDuTemps::create([
            'ec_id' => $this->propreIm->id, 'filiere_id' => $this->im->id, 'annee_id' => $autreAnnee->id, 'jour_semaine' => 1,
            'heure_debut' => '08:00', 'heure_fin' => '10:00', 'salle_id' => $this->s1->id, 'type_cours' => 'cm',
        ]);

        $this->creneau($this->commun, ['salle_id' => $this->s1->id, 'valide_au' => '2026-12-31'])->assertCreated();

        // La version suivante de l'emploi du temps reprend la salle au même moment.
        $this->creneau($this->propreIm, ['salle_id' => $this->s1->id, 'valide_du' => '2027-01-04'])->assertCreated();

        // Mais pas sur une période qui recouvre la première.
        $this->creneau($this->propreIm, ['salle_id' => $this->s1->id, 'heure_debut' => '09:00', 'heure_fin' => '10:00', 'valide_du' => '2026-12-01', 'valide_au' => '2026-12-20'])
            ->assertStatus(422);
    }

    public function test_une_seance_ne_prend_ni_la_salle_ni_la_promotion_d_une_autre(): void
    {
        $jour = Carbon::parse('next monday')->addWeeks(3)->toDateString();
        $seance = fn (Ec $ec, array $valeurs) => $this->api('POST', 'evenements', $valeurs + [
            'ec_id' => $ec->id, 'date' => $jour, 'heure_debut' => '08:00', 'heure_fin' => '10:00',
        ]);

        $seance($this->propreIm, ['salle_id' => $this->s1->id])->assertCreated();

        // Seule la salle était vérifiée : un autre cours d'IM au même moment passait.
        $seance($this->commun, ['salle_id' => $this->s2->id, 'heure_debut' => '09:00', 'heure_fin' => '11:00'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => "Conflit de promotion : {$this->im->code} a déjà {$this->propreIm->code} le " . Carbon::parse($jour)->format('d/m/Y') . ' de 08:00 à 10:00.']);

        // Deux groupes de TD, deux salles : deux séances.
        $seance($this->commun, ['type_cours' => 'td', 'groupe_id' => $this->g1->id, 'salle_id' => $this->s2->id, 'heure_debut' => '14:00', 'heure_fin' => '16:00'])->assertCreated();
        $seance($this->commun, ['type_cours' => 'td', 'groupe_id' => $this->g2->id, 'heure_debut' => '14:00', 'heure_fin' => '16:00'])->assertCreated();
    }

    public function test_modifier_un_creneau_retire_les_seances_a_venir_de_l_ancienne_version(): void
    {
        $id = $this->creneau($this->propreIm)->assertCreated()->json('data.id');
        $generee = Evenement::factory()->pourEc($this->propreIm)->create(['date' => $this->lundiProchain(), 'heure_debut' => '08:00:00', 'heure_fin' => '10:00:00']);

        $this->api('PUT', "emploi-du-temps/{$id}", [
            'ec_id' => $this->propreIm->id, 'jour_semaine' => 1, 'heure_debut' => '10:00', 'heure_fin' => '12:00', 'type_cours' => 'cm',
        ])->assertOk()->assertJsonFragment(['message' => "Créneau modifié. 1 séance(s) à venir de l'ancienne version retirée(s) : la génération de cette nuit recrée celles de la nouvelle."]);

        $this->assertSoftDeleted($generee);
    }

    public function test_la_generation_suit_la_validite_et_evite_les_seances_deja_posees(): void
    {
        $this->ouvrirSemestres($this->annee, $this->etab->id);
        $lundi = $this->lundiProchain();

        // Une version échue la veille : rien n'est généré.
        $this->creneau($this->commun, ['valide_au' => Carbon::parse($lundi)->subDay()->toDateString()])->assertCreated();
        // Un créneau qui tomberait sur une séance posée à la main dans sa salle.
        $this->creneau($this->propreIm, ['heure_debut' => '10:00', 'heure_fin' => '12:00', 'salle_id' => $this->s2->id])->assertCreated();
        $posee = Evenement::factory()->pourEc($this->commun)->create(['date' => $lundi, 'heure_debut' => '10:30:00', 'heure_fin' => '11:30:00', 'salle_id' => $this->s2->id]);

        $this->artisan('events:generate-from-schedule', ['--date' => $lundi, '--days' => 1])
            ->expectsOutputToContain('conflit avec une séance déjà posée')
            ->assertSuccessful();

        $this->assertSame(0, Evenement::where('ec_id', $this->propreIm->id)->count());
        $this->assertSame([$posee->id], Evenement::where('ec_id', $this->commun->id)->pluck('id')->all());
    }

    public function test_le_rapport_liste_les_conflits_deja_en_base(): void
    {
        $jour = '2046-11-09';
        Evenement::factory()->pourEc($this->propreIm)->create(['date' => $jour, 'salle_id' => $this->s1->id]);
        Evenement::factory()->pourEc($this->commun)->create(['date' => $jour, 'heure_debut' => '09:00:00', 'heure_fin' => '11:00:00', 'salle_id' => $this->s1->id]);

        $reponse = $this->api('GET', 'emploi-du-temps/conflits')->assertOk()->assertJsonCount(1, 'data.seances');

        $this->assertStringContainsString('Conflit de salle', implode(' ', $reponse->json('data.seances.0.motifs')));
        $this->assertStringContainsString('Conflit de promotion', implode(' ', $reponse->json('data.seances.0.motifs')));
    }

    /** L'import IA garde la version du document, le groupe et l'enseignant. */
    public function test_l_import_ia_enregistre_version_groupe_et_enseignant(): void
    {
        $this->api('POST', 'import/schedule/confirmer', [
            'filiere_id' => $this->im->id,
            'annee_id'   => $this->annee->id,
            'valide_du'  => '2026-09-21',
            'creneaux'   => [[
                'ec_code' => $this->commun->code, 'jour' => 'Mardi', 'heure_debut' => '8h', 'heure_fin' => '10h',
                'type_cours' => 'td', 'groupe' => 'g1', 'sans_salle' => true, 'enseignant' => 'M. HOUNDJI',
            ]],
        ])->assertCreated();

        $this->assertDatabaseHas('emploi_du_temps', [
            'ec_id' => $this->commun->id, 'groupe_id' => $this->g1->id, 'type_cours' => 'td',
            'valide_du' => '2026-09-21', 'enseignant' => 'HOUNDJI',
        ]);
    }

    /** Le CSV : un cours commun importé pour GL, un groupe, une version. */
    public function test_l_import_csv_recoit_groupe_enseignant_et_validite(): void
    {
        $csv = "filiere_code,niveau,annee_libelle,semestre,ue_code,ec_code,jour,heure_debut,heure_fin,salle_code,type_cours,groupe,enseignant,valide_du,valide_au\n"
            . "{$this->im->code},L2,2046-2047,3,{$this->ueCommune->code},{$this->commun->code},Mercredi,08:00,10:00,,TD,G2,Dr AGBO,21/09/2026,\n"
            . "{$this->gl->code},L2,2046-2047,3,{$this->ueCommune->code},{$this->commun->code},Jeudi,08:00,10:00,,CM,,,,\n";

        $this->api('POST', 'import/csv/schedule', ['file' => UploadedFile::fake()->createWithContent('edt.csv', $csv)])
            ->assertOk()->assertJsonPath('data.success', 2)->assertJsonPath('data.errors', []);

        $this->assertDatabaseHas('emploi_du_temps', [
            'ec_id' => $this->commun->id, 'jour_semaine' => 3, 'groupe_id' => $this->g2->id, 'enseignant' => 'AGBO', 'valide_du' => '2026-09-21',
        ]);
        $this->assertDatabaseHas('emploi_du_temps', ['ec_id' => $this->commun->id, 'jour_semaine' => 4, 'filiere_id' => $this->gl->id]);
    }
}
