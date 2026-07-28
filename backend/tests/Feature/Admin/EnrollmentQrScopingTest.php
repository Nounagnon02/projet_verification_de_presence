<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Les inscriptions pédagogiques (EnrollmentController) et la génération de QR
 * (QrCodeController) doivent être cloisonnées par établissement.
 */
class EnrollmentQrScopingTest extends TestCase
{
    private string $tokenA;
    private Etudiant $etudiantB;
    private Ec $ecB;
    private Evenement $evenementB;

    protected function setUp(): void
    {
        parent::setUp();
        $sfx = Str::random(5);
        $annee = AnneeAcademique::query()->firstOrFail();

        $etabA = DB::table('etablissements')->insertGetId(['code' => 'EA' . $sfx, 'nom' => 'A', 'email' => "ea$sfx@x.test", 'actif' => true, 'created_at' => now(), 'updated_at' => now()]);
        $etabB = DB::table('etablissements')->insertGetId(['code' => 'EB' . $sfx, 'nom' => 'B', 'email' => "eb$sfx@x.test", 'actif' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->tokenA = User::factory()->create([
            'email' => "adm-$sfx@x.test", 'role' => 'faculte_admin', 'etablissement_id' => $etabA,
        ])->createToken('t')->plainTextToken;

        $filiereB = Filiere::create(['code' => 'FB' . $sfx, 'intitule' => 'B', 'niveau' => 'L1', 'etablissement_id' => $etabB]);
        $ueB = Ue::create(['code' => 'UB' . $sfx, 'intitule' => 'U', 'filiere_id' => $filiereB->id, 'annee_id' => $annee->id, 'semestre' => 1, 'volume_horaire' => 20]);
        $this->ecB = Ec::create(['ue_id' => $ueB->id, 'code' => 'CB' . $sfx, 'intitule' => 'C', 'volume_horaire' => 20]);
        $this->etudiantB = Etudiant::create([
            'nom' => 'B', 'prenom' => 'B', 'matricule' => "B-$sfx",
            'filiere_id' => $filiereB->id, 'annee_id' => $annee->id,
            'email' => "etu-$sfx@x.test", 'identifiant_unique' => "B_$sfx",
        ]);
        $this->evenementB = Evenement::create([
            'ec_id' => $this->ecB->id, 'filiere_id' => $filiereB->id, 'annee_id' => $annee->id,
            'date' => today()->toDateString(), 'heure_debut' => '08:00:00', 'heure_fin' => '10:00:00', 'statut' => 'en_cours',
        ]);
    }

    private function asA()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->tokenA);
    }

    public function test_lecture_inscriptions_dun_etudiant_dune_autre_faculte_refusee(): void
    {
        $this->asA()->getJson("/api/admin/students/{$this->etudiantB->id}/ecs")->assertStatus(404);
    }

    public function test_inscription_ec_dun_etudiant_dune_autre_faculte_refusee(): void
    {
        $this->asA()->postJson("/api/admin/students/{$this->etudiantB->id}/ecs", ['ec_ids' => [$this->ecB->id]])->assertStatus(404);
    }

    public function test_reset_inscriptions_dune_autre_faculte_refuse(): void
    {
        $this->asA()->postJson("/api/admin/students/{$this->etudiantB->id}/ecs/reset")->assertStatus(404);
    }

    public function test_generation_qr_pour_evenement_dune_autre_faculte_refusee(): void
    {
        $this->asA()->getJson("/api/admin/qrcode/{$this->evenementB->id}/generate")->assertStatus(404);
    }
}
