<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Salle;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Salles lues dans un document d'import : reconnaissance et création en un clic.
 *
 * L'écran de validation de l'import IA pré-remplit chaque salle reconnue et
 * propose « Créer X » pour les autres. Les deux endpoints appliquent la règle
 * commune des imports (CorrespondanceSalles) : établissement de la filière
 * seulement, sans tenir compte de la casse, des accents ni de la ponctuation.
 */
class SallesImportTest extends TestCase
{
    private string $sfx;
    private int $etabA;
    private int $etabB;
    private Filiere $filiereA;
    private Filiere $filiereB;
    private Salle $quartz;
    private string $jetonA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx = Str::random(6);
        $this->etabA = $this->etablissement('A');
        $this->etabB = $this->etablissement('B');

        $this->filiereA = Filiere::create([
            'code' => 'SIA' . $this->sfx, 'intitule' => 'Filière A', 'niveau' => 'L1', 'etablissement_id' => $this->etabA,
        ]);
        $this->filiereB = Filiere::create([
            'code' => 'SIB' . $this->sfx, 'intitule' => 'Filière B', 'niveau' => 'L1', 'etablissement_id' => $this->etabB,
        ]);

        $this->quartz = Salle::create([
            'nom' => "Amphi Quartz {$this->sfx}", 'code' => 'AQ' . $this->sfx, 'actif' => true, 'etablissement_id' => $this->etabA,
        ]);

        $this->jetonA = User::factory()->faculteAdmin($this->etabA)
            ->create(['email' => "salles-a-{$this->sfx}@test.local"])
            ->createToken('test')->plainTextToken;
    }

    private function etablissement(string $lettre): int
    {
        return DB::table('etablissements')->insertGetId([
            'code' => $lettre . $this->sfx, 'nom' => "Établissement {$lettre} {$this->sfx}",
            'email' => strtolower("salles-{$lettre}-{$this->sfx}@test.local"), 'actif' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function reconnaitre(array $noms, ?Filiere $filiere = null)
    {
        return $this->withToken($this->jetonA)->postJson('/api/admin/salles/reconnaitre', [
            'filiere_id' => ($filiere ?? $this->filiereA)->id,
            'noms'       => $noms,
        ]);
    }

    private function creer(string $nom, ?Filiere $filiere = null)
    {
        return $this->withToken($this->jetonA)->postJson('/api/admin/salles/depuis-nom', [
            'filiere_id' => ($filiere ?? $this->filiereA)->id,
            'nom'        => $nom,
        ]);
    }

    // ── Reconnaissance ─────────────────────────────────────────────────

    public function test_reconnait_une_salle_malgre_casse_accents_et_ponctuation(): void
    {
        $lignes = collect($this->reconnaitre([
            "amphi  QUARTZ {$this->sfx}",
            "Àmphi-Quartz {$this->sfx}",
            'Salle inconnue',
        ])->assertOk()->json('data'))->keyBy('nom');

        $this->assertSame($this->quartz->id, $lignes["amphi  QUARTZ {$this->sfx}"]['salle']['id']);
        $this->assertSame($this->quartz->id, $lignes["Àmphi-Quartz {$this->sfx}"]['salle']['id']);
        $this->assertNull($lignes['Salle inconnue']['salle']);
        // Ce que la salle vérifie au scan est joint, pour dire « QR seul ».
        $this->assertFalse($lignes["amphi  QUARTZ {$this->sfx}"]['salle']['verifie_gps']);
    }

    public function test_une_salle_desactivee_n_est_pas_proposee(): void
    {
        Salle::create([
            'nom' => "Labo Ancien {$this->sfx}", 'code' => 'LA' . $this->sfx, 'actif' => false, 'etablissement_id' => $this->etabA,
        ]);

        $ligne = $this->reconnaitre(["labo ancien {$this->sfx}"])->assertOk()->json('data.0');

        $this->assertNull($ligne['salle']);
        $this->assertTrue($ligne['desactivee']);
    }

    public function test_la_reconnaissance_ne_regarde_que_l_etablissement_de_la_filiere(): void
    {
        Salle::create([
            'nom' => "Salle Béta {$this->sfx}", 'code' => 'SBT' . $this->sfx, 'actif' => true, 'etablissement_id' => $this->etabB,
        ]);

        $this->assertNull($this->reconnaitre(["Salle Béta {$this->sfx}"])->assertOk()->json('data.0.salle'));

        // La filière d'un autre établissement n'est pas une destination possible.
        $this->reconnaitre(['Amphi'], $this->filiereB)->assertStatus(404);
    }

    // ── Création en un clic ────────────────────────────────────────────

    public function test_cree_une_salle_qr_seul_une_seule_fois(): void
    {
        $premiere = $this->creer("Labo 3 {$this->sfx}")->assertCreated();

        $this->assertFalse($premiere->json('data.verifie_gps'));
        $this->assertFalse($premiere->json('data.verifie_wifi'));
        $this->assertStringContainsString('GPS et Wi-Fi à configurer', $premiere->json('message'));

        // Un second clic, même avec une autre casse, rend la même salle.
        $seconde = $this->creer("  labo 3 {$this->sfx}")->assertOk();

        $this->assertSame($premiere->json('data.id'), $seconde->json('data.id'));
        $this->assertSame(1, Salle::where('etablissement_id', $this->etabA)->where('nom', "Labo 3 {$this->sfx}")->count());
    }

    public function test_ne_recree_pas_une_salle_desactivee(): void
    {
        Salle::create([
            'nom' => "Labo Ancien {$this->sfx}", 'code' => 'LX' . $this->sfx, 'actif' => false, 'etablissement_id' => $this->etabA,
        ]);

        $this->creer("labo ancien {$this->sfx}")->assertStatus(422);

        $this->assertSame(1, Salle::where('etablissement_id', $this->etabA)->where('nom', "Labo Ancien {$this->sfx}")->count());
    }

    public function test_ne_cree_rien_pour_la_filiere_d_un_autre_etablissement(): void
    {
        $this->creer("Salle Pirate {$this->sfx}", $this->filiereB)->assertStatus(404);

        $this->assertDatabaseMissing('salles', ['nom' => "Salle Pirate {$this->sfx}"]);
    }

    // ── Ancien import d'événements datés ───────────────────────────────

    /**
     * /import/validate-events acceptait un nom de salle libre. La salle s'y
     * désigne désormais par son identifiant, dans l'établissement de la filière.
     */
    public function test_l_import_d_evenements_refuse_la_salle_d_un_autre_etablissement(): void
    {
        $annee = AnneeAcademique::create([
            'libelle' => "SI-{$this->sfx}", 'date_debut' => '2090-09-01', 'date_fin' => '2091-07-31', 'active' => false,
        ]);
        $ue = Ue::create([
            'code' => 'SIUE' . $this->sfx, 'intitule' => 'UE', 'filiere_id' => $this->filiereA->id,
            'annee_id' => $annee->id, 'semestre' => 1, 'volume_horaire' => 60,
        ]);
        $ec = Ec::create(['ue_id' => $ue->id, 'code' => 'SIEC' . $this->sfx, 'intitule' => 'EC', 'volume_horaire' => 60]);
        $salleB = Salle::create([
            'nom' => "Salle B {$this->sfx}", 'code' => 'SLB' . $this->sfx, 'actif' => true, 'etablissement_id' => $this->etabB,
        ]);

        $evenement = fn (array $salle) => array_merge([
            'ec_id' => $ec->id, 'filiere_id' => $this->filiereA->id, 'annee_id' => $annee->id,
            'date' => '2090-10-06', 'heure_debut' => '08:00', 'heure_fin' => '10:00',
        ], $salle);

        $reponse = $this->withToken($this->jetonA)->postJson('/api/admin/import/validate-events', [
            'events' => [
                $evenement(['salle_id' => $salleB->id]),
                $evenement(['salle_id' => $this->quartz->id, 'heure_debut' => '10:00', 'heure_fin' => '12:00']),
            ],
        ])->assertCreated();

        $this->assertSame(1, $reponse->json('data.total'));
        $this->assertCount(1, $reponse->json('data.refuses'));
        $this->assertDatabaseHas('evenements', ['ec_id' => $ec->id, 'salle_id' => $this->quartz->id, 'salle' => $this->quartz->nom]);
        $this->assertSame(0, Evenement::where('ec_id', $ec->id)->where('salle_id', $salleB->id)->count());
    }
}
