<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Les clés étrangères sont en ON DELETE CASCADE : supprimer une filière
 * effaçait en base ses UE, EC, événements, étudiants et présences.
 * Le garde preventDeleteWithDependencies() doit bloquer l'opération, et les
 * données à valeur de preuve doivent être en suppression réversible.
 */
class CascadeDeleteGuardTest extends TestCase
{
    private function admin(): string
    {
        return User::factory()->create([
            'email' => 'cascade-' . Str::random(6) . '@example.test',
            'role'  => 'super_admin',
        ])->createToken('test')->plainTextToken;
    }

    public function test_suppression_filiere_bloquee_si_elle_a_des_etudiants(): void
    {
        $token = $this->admin();

        $filiere = Filiere::create([
            'code' => 'CAS' . Str::random(5), 'intitule' => 'Cascade', 'niveau' => 'L1',
        ]);
        $etudiant = Etudiant::create([
            'nom' => 'CASCADE', 'prenom' => 'CASCADE', 'matricule' => 'CAS-' . Str::random(6),
            'filiere_id' => $filiere->id, 'annee_id' => AnneeAcademique::query()->firstOrFail()->id,
            'email' => 'cas-' . Str::random(6) . '@example.test', 'identifiant_unique' => 'CAS_' . Str::random(8),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/admin/filieres/' . $filiere->id)
            ->assertStatus(409);

        // La filière et l'étudiant existent toujours.
        $this->assertNotNull(Filiere::find($filiere->id));
        $this->assertNotNull(Etudiant::find($etudiant->id));
    }

    public function test_suppression_filiere_vide_autorisee(): void
    {
        $token = $this->admin();
        $filiere = Filiere::create([
            'code' => 'VIDE' . Str::random(4), 'intitule' => 'Vide', 'niveau' => 'L1',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/admin/filieres/' . $filiere->id)
            ->assertStatus(200);
    }

    public function test_la_suppression_dun_etudiant_est_reversible(): void
    {
        $etudiant = Etudiant::create([
            'nom' => 'SOFT', 'prenom' => 'SOFT', 'matricule' => 'SOF-' . Str::random(6),
            'filiere_id' => Filiere::query()->firstOrFail()->id,
            'annee_id' => AnneeAcademique::query()->firstOrFail()->id,
            'email' => 'soft-' . Str::random(6) . '@example.test', 'identifiant_unique' => 'SOF_' . Str::random(8),
        ]);

        $etudiant->delete();

        // Absent des requêtes normales, mais récupérable.
        $this->assertNull(Etudiant::find($etudiant->id));
        $this->assertNotNull(Etudiant::withTrashed()->find($etudiant->id));
    }
}
