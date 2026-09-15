<?php

namespace Tests\Feature\Admin;

use App\Models\Anomaly;
use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Les scans refusés doivent être cloisonnés par établissement : un admin de
 * faculté ne voit pas ceux des étudiants des autres facultés.
 */
class AnomalyScopingTest extends TestCase
{
    private string $tokenA;
    private Anomaly $anomalieB;

    protected function setUp(): void
    {
        parent::setUp();
        $sfx = Str::random(5);

        $etabA = DB::table('etablissements')->insertGetId(['code' => 'AA' . $sfx, 'nom' => 'A', 'email' => "a$sfx@x.test", 'actif' => true, 'created_at' => now(), 'updated_at' => now()]);
        $etabB = DB::table('etablissements')->insertGetId(['code' => 'BB' . $sfx, 'nom' => 'B', 'email' => "b$sfx@x.test", 'actif' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->tokenA = User::factory()->create([
            'email' => "admin-a-$sfx@x.test", 'role' => 'faculte_admin', 'etablissement_id' => $etabA,
        ])->createToken('t')->plainTextToken;

        $filiereB = Filiere::create(['code' => 'FB' . $sfx, 'intitule' => 'B', 'niveau' => 'L1', 'etablissement_id' => $etabB]);
        $etudiantB = Etudiant::create([
            'nom' => 'B', 'prenom' => 'B', 'matricule' => "B-$sfx",
            'filiere_id' => $filiereB->id, 'annee_id' => $this->anneeActive()->id,
            'email' => "etu-b-$sfx@x.test", 'identifiant_unique' => "B_$sfx",
        ]);
        $this->anomalieB = Anomaly::create([
            'etudiant_id' => $etudiantB->id, 'type' => 'verification_echouee',
            'description' => 'secret faculté B', 'severity' => 'high', 'resolved' => false,
        ]);
    }

    public function test_admin_a_ne_voit_pas_les_anomalies_de_b(): void
    {
        $r = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)->getJson('/api/admin/alerts');
        $r->assertStatus(200);
        $ids = collect($r->json('data.data'))->pluck('id')->all();
        $this->assertNotContains($this->anomalieB->id, $ids);
    }
}
