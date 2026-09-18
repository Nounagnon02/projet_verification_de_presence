<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Salle;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cloisonnement des écritures et des rapports entre facultés.
 *
 * Régression : ces endpoints ne vérifiaient que le rôle, ou n'acceptaient
 * l'identifiant d'une ressource qu'à travers « exists: », qui ne dit rien de
 * l'établissement. Un admin de faculté pouvait :
 * - télécharger la feuille d'émargement de n'importe quelle séance ;
 * - lire le rapport d'une filière d'une autre faculté ;
 * - créer ou déplacer une séance sur le cours ou dans la salle d'une autre ;
 * - inscrire un étudiant dans une autre faculté, ou y déplacer le sien ;
 * - importer des séances sur le cours d'une autre faculté.
 *
 * Chaque refus répond 404 (403 pour l'import en lot, comme pour ses filières)
 * et ne laisse aucune trace en base. Le super admin n'est pas cloisonné.
 */
class IsolationEcrituresEtRapportsTest extends TestCase
{
    private string $jetonA;

    private string $jetonSuper;

    private AnneeAcademique $annee;

    private Filiere $filiereA;

    private Filiere $filiereB;

    private Ec $ecA;

    private Ec $ecB;

    private Salle $salleA;

    private Salle $salleB;

    private Evenement $seanceA;

    private Evenement $seanceB;

    protected function setUp(): void
    {
        parent::setUp();

        $sfx = Str::random(5);
        $etabA = Etablissement::factory()->create();
        $etabB = Etablissement::factory()->create();

        $this->jetonA = User::factory()->faculteAdmin($etabA->id)->create()->createToken('test')->plainTextToken;
        $this->jetonSuper = User::factory()->create()->createToken('test')->plainTextToken;

        $this->annee = $this->anneeActive();

        $this->filiereA = Filiere::create(['code' => 'ISA'.$sfx, 'intitule' => 'Filière A', 'niveau' => 'L1', 'etablissement_id' => $etabA->id]);
        $this->filiereB = Filiere::create(['code' => 'ISB'.$sfx, 'intitule' => 'Filière B', 'niveau' => 'L1', 'etablissement_id' => $etabB->id]);

        $this->ecA = $this->unCours($this->filiereA, 'A'.$sfx);
        $this->ecB = $this->unCours($this->filiereB, 'B'.$sfx);

        $this->salleA = Salle::factory()->create(['etablissement_id' => $etabA->id]);
        $this->salleB = Salle::factory()->create(['etablissement_id' => $etabB->id]);

        $this->seanceA = $this->uneSeance($this->ecA, $this->filiereA, '08:00', '09:00');
        $this->seanceB = $this->uneSeance($this->ecB, $this->filiereB, '08:00', '09:00');
    }

    private function unCours(Filiere $filiere, string $sfx): Ec
    {
        $ue = Ue::create([
            'code' => 'UE-'.$sfx, 'intitule' => 'UE '.$sfx, 'filiere_id' => $filiere->id,
            'annee_id' => $this->annee->id, 'semestre' => 1, 'volume_horaire' => 60,
        ]);

        return Ec::create(['ue_id' => $ue->id, 'code' => 'EC-'.$sfx, 'intitule' => 'EC '.$sfx, 'volume_horaire' => 40]);
    }

    private function uneSeance(Ec $ec, Filiere $filiere, string $debut, string $fin): Evenement
    {
        return Evenement::create([
            'ec_id' => $ec->id, 'filiere_id' => $filiere->id, 'annee_id' => $this->annee->id,
            'date' => today()->format('Y-m-d'), 'heure_debut' => $debut, 'heure_fin' => $fin, 'statut' => 'planifie',
        ]);
    }

    /** @return array<string, mixed> */
    private function nouvelleSeance(Ec $ec, array $extra = []): array
    {
        return $extra + [
            'ec_id' => $ec->id, 'date' => today()->format('Y-m-d'),
            'heure_debut' => '14:00', 'heure_fin' => '15:00',
        ];
    }

    private function commeAdminA()
    {
        return $this->withToken($this->jetonA);
    }

    // ─── Rapports ──────────────────────────────────────────────────────────

    public function test_la_feuille_d_emargement_d_une_autre_faculte_est_refusee(): void
    {
        $this->commeAdminA()->get("/api/admin/reports/presence/{$this->seanceB->id}/pdf")
            ->assertNotFound();
    }

    public function test_la_feuille_d_emargement_de_sa_faculte_reste_accessible(): void
    {
        $this->commeAdminA()->get("/api/admin/reports/presence/{$this->seanceA->id}/pdf")
            ->assertOk();
    }

    public function test_le_rapport_d_une_filiere_d_une_autre_faculte_est_refuse(): void
    {
        $this->commeAdminA()->getJson("/api/admin/reports/department/{$this->filiereB->id}")
            ->assertNotFound();

        $this->commeAdminA()->get("/api/admin/reports/department/{$this->filiereB->id}?format=pdf")
            ->assertNotFound();
    }

    public function test_le_rapport_d_une_filiere_de_sa_faculte_reste_accessible(): void
    {
        $this->commeAdminA()->getJson("/api/admin/reports/department/{$this->filiereA->id}")
            ->assertOk();
    }

    // ─── Séances ───────────────────────────────────────────────────────────

    public function test_creer_une_seance_sur_le_cours_d_une_autre_faculte_est_refuse(): void
    {
        $this->commeAdminA()->postJson('/api/admin/evenements', $this->nouvelleSeance($this->ecB))
            ->assertNotFound();

        $this->assertDatabaseMissing('evenements', ['ec_id' => $this->ecB->id, 'heure_debut' => '14:00']);
    }

    public function test_creer_une_seance_dans_la_salle_d_une_autre_faculte_est_refuse(): void
    {
        $this->commeAdminA()->postJson('/api/admin/evenements', $this->nouvelleSeance($this->ecA, ['salle_id' => $this->salleB->id]))
            ->assertNotFound();

        $this->assertDatabaseMissing('evenements', ['salle_id' => $this->salleB->id]);
    }

    public function test_creer_une_seance_sur_son_cours_et_dans_sa_salle_reste_possible(): void
    {
        $this->commeAdminA()->postJson('/api/admin/evenements', $this->nouvelleSeance($this->ecA, ['salle_id' => $this->salleA->id]))
            ->assertCreated();
    }

    public function test_deplacer_une_seance_sur_le_cours_d_une_autre_faculte_est_refuse(): void
    {
        $this->commeAdminA()->putJson("/api/admin/evenements/{$this->seanceA->id}", ['ec_id' => $this->ecB->id])
            ->assertNotFound();

        $this->assertSame($this->ecA->id, $this->seanceA->fresh()->ec_id);
        $this->assertSame($this->filiereA->id, $this->seanceA->fresh()->filiere_id);
    }

    public function test_deplacer_une_seance_dans_la_salle_d_une_autre_faculte_est_refuse(): void
    {
        $this->commeAdminA()->putJson("/api/admin/evenements/{$this->seanceA->id}", ['salle_id' => $this->salleB->id])
            ->assertNotFound();

        $this->assertNull($this->seanceA->fresh()->salle_id);
    }

    // ─── Étudiants ─────────────────────────────────────────────────────────

    public function test_inscrire_un_etudiant_dans_une_autre_faculte_est_refuse(): void
    {
        $matricule = 'ISO-'.Str::random(6);

        $this->commeAdminA()->postJson('/api/admin/students', [
            'nom' => 'Hounkpe', 'prenom' => 'Aline', 'matricule' => $matricule,
            'email' => strtolower($matricule).'@etu.test', 'filiere_id' => $this->filiereB->id,
        ])->assertNotFound();

        $this->assertDatabaseMissing('etudiants', ['matricule' => $matricule]);
    }

    public function test_deplacer_son_etudiant_dans_une_autre_faculte_est_refuse(): void
    {
        $sfx = Str::random(6);
        $etudiant = Etudiant::create([
            'nom' => 'ISOLE', 'prenom' => 'ETUDIANT', 'matricule' => 'ISO-'.$sfx,
            'filiere_id' => $this->filiereA->id, 'annee_id' => $this->annee->id,
            'email' => 'iso-'.strtolower($sfx).'@etu.test', 'identifiant_unique' => 'ISO_'.$sfx,
        ]);

        $this->commeAdminA()->putJson("/api/admin/students/{$etudiant->id}", ['filiere_id' => $this->filiereB->id])
            ->assertNotFound();

        $this->assertSame($this->filiereA->id, $etudiant->fresh()->filiere_id);
    }

    // ─── Import de séances ─────────────────────────────────────────────────

    public function test_importer_des_seances_sur_le_cours_d_une_autre_faculte_est_refuse(): void
    {
        $this->commeAdminA()->postJson('/api/admin/import/validate-events', ['events' => [[
            'ec_id' => $this->ecB->id, 'filiere_id' => $this->filiereA->id, 'annee_id' => $this->annee->id,
            'date' => today()->format('Y-m-d'), 'heure_debut' => '16:00', 'heure_fin' => '17:00',
        ]]])->assertForbidden();

        $this->assertDatabaseMissing('evenements', ['ec_id' => $this->ecB->id, 'heure_debut' => '16:00']);
    }

    // ─── Super admin ───────────────────────────────────────────────────────

    public function test_le_super_admin_n_est_pas_cloisonne(): void
    {
        $this->withToken($this->jetonSuper)->get("/api/admin/reports/presence/{$this->seanceB->id}/pdf")
            ->assertOk();

        $this->withToken($this->jetonSuper)->postJson('/api/admin/evenements', $this->nouvelleSeance($this->ecB, ['salle_id' => $this->salleB->id]))
            ->assertCreated();
    }
}
