<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Filtres académiques en cascade.
 *
 * Régression : GET /admin/filieres ignorait toute notion d'année. Les écrans
 * proposaient donc les onze filières et « Semestre 1 à 6 » quelle que soit
 * l'année choisie, y compris une année totalement vide — d'où des listes sans
 * résultat que rien n'expliquait à l'écran.
 *
 * Deux pièges que ces tests verrouillent :
 *
 *  - Le pivot « filiere_annee » n'est PAS fiable : il n'est alimenté qu'à la
 *    création d'une filière, et StudentPromotionService déplace les étudiants
 *    d'une année à l'autre sans y toucher. Une cascade fondée sur lui seul
 *    masquerait des filières pourtant peuplées.
 *
 *  - Les semestres réels vont de 1 à 10. La plage codée en dur s'arrêtait à 6,
 *    rendant tout le Master infiltrable.
 */
class FiltresCascadeTest extends TestCase
{
    private string $token;
    private AnneeAcademique $anneePleine;
    private AnneeAcademique $anneeVide;
    private Filiere $avecEtudiants;
    private Filiere $avecUes;
    private Filiere $horsAnnee;

    protected function setUp(): void
    {
        parent::setUp();

        $suffixe = Str::random(6);

        $admin = User::factory()->create([
            'email' => 'cascade-' . $suffixe . '@example.test',
            'role'  => 'admin',
        ]);
        $this->token = $admin->createToken('test')->plainTextToken;

        // Bande d'années réservée aux tests, pour ne heurter aucune donnée
        // existante : le libellé porte une contrainte d'unicité globale.
        $this->anneePleine = AnneeAcademique::create([
            'libelle' => '2080-2081 ' . $suffixe, 'date_debut' => '2080-09-01',
            'date_fin' => '2081-07-31', 'active' => false,
        ]);
        $this->anneeVide = AnneeAcademique::create([
            'libelle' => '2081-2082 ' . $suffixe, 'date_debut' => '2081-09-01',
            'date_fin' => '2082-07-31', 'active' => false,
        ]);

        $this->avecEtudiants = Filiere::create([
            'code' => 'CAS1' . $suffixe, 'intitule' => 'Avec étudiants', 'niveau' => 'L1',
        ]);
        $this->avecUes = Filiere::create([
            'code' => 'CAS2' . $suffixe, 'intitule' => 'Avec UEs', 'niveau' => 'M2',
        ]);
        $this->horsAnnee = Filiere::create([
            'code' => 'CAS3' . $suffixe, 'intitule' => 'Sans rattachement', 'niveau' => 'L3',
        ]);

        // Rattachée par les seules données, SANS ligne de pivot : c'est le cas
        // réel de DEMO-IM-L1 en base de développement.
        Etudiant::factory()->create([
            'filiere_id' => $this->avecEtudiants->id,
            'annee_id'   => $this->anneePleine->id,
        ]);

        // Semestres 9 et 10 : hors de la plage 1-6 codée en dur côté client.
        foreach ([9, 10] as $semestre) {
            Ue::create([
                'code'       => 'UE' . $semestre . $suffixe,
                'intitule'   => 'UE semestre ' . $semestre,
                'volume_horaire' => 30,
                'semestre'   => $semestre,
                'filiere_id' => $this->avecUes->id,
                'annee_id'   => $this->anneePleine->id,
            ]);
        }
    }

    private function filieresPour(?int $anneeId): array
    {
        $url = '/api/admin/filieres' . ($anneeId ? '?annee_id=' . $anneeId : '');

        return $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson($url)
            ->assertOk()
            ->json('data');
    }

    public function test_une_annee_sans_contenu_ne_renvoie_aucune_filiere(): void
    {
        $codes = array_column($this->filieresPour($this->anneeVide->id), 'code');

        $this->assertNotContains($this->avecEtudiants->code, $codes);
        $this->assertNotContains($this->avecUes->code, $codes);
        $this->assertNotContains($this->horsAnnee->code, $codes);
    }

    public function test_une_filiere_est_rattachee_par_ses_etudiants_meme_sans_pivot(): void
    {
        $this->assertDatabaseMissing('filiere_annee', [
            'filiere_id' => $this->avecEtudiants->id,
            'annee_id'   => $this->anneePleine->id,
        ]);

        $codes = array_column($this->filieresPour($this->anneePleine->id), 'code');

        $this->assertContains($this->avecEtudiants->code, $codes);
    }

    public function test_une_filiere_est_rattachee_par_ses_ues(): void
    {
        $codes = array_column($this->filieresPour($this->anneePleine->id), 'code');

        $this->assertContains($this->avecUes->code, $codes);
    }

    public function test_une_filiere_sans_contenu_ni_pivot_est_ecartee_de_l_annee(): void
    {
        $codes = array_column($this->filieresPour($this->anneePleine->id), 'code');

        $this->assertNotContains($this->horsAnnee->code, $codes);
    }

    public function test_le_pivot_suffit_pour_une_filiere_encore_vide(): void
    {
        // Une filière fraîchement créée n'a ni étudiant ni UE ; il faut pouvoir
        // la choisir pour y inscrire le premier étudiant.
        $this->horsAnnee->anneesAcademiques()->attach($this->anneePleine->id);

        $codes = array_column($this->filieresPour($this->anneePleine->id), 'code');

        $this->assertContains($this->horsAnnee->code, $codes);
    }

    public function test_les_semestres_de_master_sont_exposes(): void
    {
        $filieres = collect($this->filieresPour($this->anneePleine->id));
        $cible = $filieres->firstWhere('code', $this->avecUes->code);

        $this->assertNotNull($cible, 'La filière porteuse des UEs doit être renvoyée.');
        $this->assertSame([9, 10], $cible['semestres']);
    }

    public function test_les_semestres_sont_renseignes_meme_sans_annee(): void
    {
        // Contrat uniforme : le client n'a pas à distinguer « aucun semestre »
        // d'un champ absent.
        $filieres = collect($this->filieresPour(null));
        $cible = $filieres->firstWhere('code', $this->avecUes->code);

        $this->assertNotNull($cible);
        $this->assertArrayHasKey('semestres', $cible);
        $this->assertSame([9, 10], $cible['semestres']);
    }

    public function test_sans_annee_toutes_les_filieres_restent_visibles(): void
    {
        $codes = array_column($this->filieresPour(null), 'code');

        $this->assertContains($this->avecEtudiants->code, $codes);
        $this->assertContains($this->avecUes->code, $codes);
        $this->assertContains($this->horsAnnee->code, $codes);
    }

    public function test_un_annee_id_invalide_ne_provoque_pas_d_erreur(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/admin/filieres?annee_id=pas-un-entier')
            ->assertOk();
    }
}
