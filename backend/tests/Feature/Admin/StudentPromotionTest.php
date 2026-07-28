<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentPromotionTest extends TestCase
{
    private function token(): string
    {
        return User::factory()->create([
            'email' => 'promo-' . Str::random(6) . '@example.test',
            'role'  => 'super_admin',
        ])->createToken('t')->plainTextToken;
    }

    private function etudiant(Filiere $f, AnneeAcademique $a): Etudiant
    {
        return Etudiant::create([
            'nom' => 'PROMO', 'prenom' => 'PROMO', 'matricule' => 'PRM-' . Str::random(6),
            'filiere_id' => $f->id, 'annee_id' => $a->id,
            'email' => 'prm-' . Str::random(6) . '@example.test',
            'identifiant_unique' => 'PRM_' . Str::random(8),
        ]);
    }

    public function test_promotion_change_filiere_et_conserve_identifiant(): void
    {
        $suffixe = Str::random(4);
        $l1 = Filiere::create(['code' => 'L1' . $suffixe, 'intitule' => 'Niv 1', 'niveau' => 'L1']);
        $l2 = Filiere::create(['code' => 'L2' . $suffixe, 'intitule' => 'Niv 2', 'niveau' => 'L2']);
        $annee = AnneeAcademique::query()->firstOrFail();

        $etudiant = $this->etudiant($l1, $annee);
        $identifiantAvant = $etudiant->identifiant_unique;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->postJson('/api/admin/students/promote', [
                'from_filiere_id' => $l1->id,
                'to_filiere_id'   => $l2->id,
            ]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.etudiants_promus'));

        $etudiant->refresh();
        $this->assertSame($l2->id, $etudiant->filiere_id);
        // L'identifiant de connexion ne doit pas changer lors d'une promotion.
        $this->assertSame($identifiantAvant, $etudiant->identifiant_unique);
    }

    public function test_dry_run_ne_modifie_rien(): void
    {
        $suffixe = Str::random(4);
        $l1 = Filiere::create(['code' => 'DRA' . $suffixe, 'intitule' => 'A', 'niveau' => 'L1']);
        $l2 = Filiere::create(['code' => 'DRB' . $suffixe, 'intitule' => 'B', 'niveau' => 'L2']);
        $etudiant = $this->etudiant($l1, AnneeAcademique::query()->firstOrFail());

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->postJson('/api/admin/students/promote', [
                'from_filiere_id' => $l1->id,
                'to_filiere_id'   => $l2->id,
                'dry_run'         => true,
            ]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.etudiants_concernes'));
        // Rien n'a bougé.
        $this->assertSame($l1->id, $etudiant->fresh()->filiere_id);
    }

    public function test_filieres_identiques_refusees(): void
    {
        $f = Filiere::create(['code' => 'SAME' . Str::random(4), 'intitule' => 'X', 'niveau' => 'L1']);

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->postJson('/api/admin/students/promote', [
                'from_filiere_id' => $f->id,
                'to_filiere_id'   => $f->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('to_filiere_id');
    }
}
