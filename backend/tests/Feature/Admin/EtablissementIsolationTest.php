<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Régression : le cloisonnement par établissement n'était appliqué qu'aux
 * listes. Les endpoints à route model binding (show/update/destroy)
 * acceptaient n'importe quel ID, permettant à un admin de faculté de lire,
 * modifier ou supprimer les ressources d'une autre faculté.
 */
class EtablissementIsolationTest extends TestCase
{
    private string $token;
    private Etudiant $etudiantAutreFaculte;
    private Filiere $filiereAutreFaculte;

    protected function setUp(): void
    {
        parent::setUp();

        $suffixe = Str::random(6);

        $etabA = DB::table('etablissements')->insertGetId([
            'code' => 'A' . $suffixe, 'nom' => 'Faculté A ' . $suffixe,
            'email' => 'fac-a-' . $suffixe . '@example.test', 'actif' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $etabB = DB::table('etablissements')->insertGetId([
            'code' => 'B' . $suffixe, 'nom' => 'Faculté B ' . $suffixe,
            'email' => 'fac-b-' . $suffixe . '@example.test', 'actif' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $adminA = User::factory()->create([
            'email'           => 'admin-a-' . $suffixe . '@example.test',
            'role'            => 'faculte_admin',
            'etablissement_id' => $etabA,
        ]);
        $this->token = $adminA->createToken('test')->plainTextToken;

        $this->filiereAutreFaculte = Filiere::create([
            'code' => 'FB' . $suffixe, 'intitule' => 'Filière B', 'niveau' => 'L1',
            'etablissement_id' => $etabB,
        ]);

        $this->etudiantAutreFaculte = Etudiant::create([
            'nom' => 'ISOTEST', 'prenom' => 'ISOTEST',
            'matricule' => 'ISO-' . $suffixe,
            'filiere_id' => $this->filiereAutreFaculte->id,
            'annee_id'   => AnneeAcademique::query()->firstOrFail()->id,
            'email' => 'iso-' . $suffixe . '@example.test',
            'identifiant_unique' => 'ISO_' . $suffixe,
        ]);
    }

    private function asAdminA()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    public function test_lecture_dun_etudiant_dune_autre_faculte_refusee(): void
    {
        $this->asAdminA()
            ->getJson('/api/admin/students/' . $this->etudiantAutreFaculte->id)
            ->assertStatus(404);
    }

    public function test_modification_dun_etudiant_dune_autre_faculte_refusee(): void
    {
        $this->asAdminA()
            ->putJson('/api/admin/students/' . $this->etudiantAutreFaculte->id, ['nom' => 'PIRATE'])
            ->assertStatus(404);

        $this->assertSame('ISOTEST', $this->etudiantAutreFaculte->fresh()->nom);
    }

    public function test_suppression_dun_etudiant_dune_autre_faculte_refusee(): void
    {
        $this->asAdminA()
            ->deleteJson('/api/admin/students/' . $this->etudiantAutreFaculte->id)
            ->assertStatus(404);

        $this->assertNotNull($this->etudiantAutreFaculte->fresh());
    }

    public function test_lecture_dune_filiere_dune_autre_faculte_refusee(): void
    {
        $this->asAdminA()
            ->getJson('/api/admin/filieres/' . $this->filiereAutreFaculte->id)
            ->assertStatus(404);
    }

    public function test_la_liste_des_etudiants_exclut_les_autres_facultes(): void
    {
        $reponse = $this->asAdminA()->getJson('/api/admin/students?per_page=100');
        $reponse->assertStatus(200);

        $ids = collect($reponse->json('data'))->pluck('id')->all();
        $this->assertNotContains($this->etudiantAutreFaculte->id, $ids);
    }
}
