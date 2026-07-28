<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Règles métier d'inscription :
 * - le matricule est obligatoire (identité officielle → identifiant stable) ;
 * - l'année est toujours l'année active, imposée par le serveur, même si le
 *   client en envoie une autre.
 */
class StudentRegistrationRulesTest extends TestCase
{
    private function token(): string
    {
        return User::factory()->create([
            'email' => 'reg-' . Str::random(6) . '@example.test',
            'role'  => 'super_admin',
        ])->createToken('t')->plainTextToken;
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'nom'        => 'REGLE',
            'prenom'     => 'Test',
            'matricule'  => 'MAT-' . Str::random(6),
            'filiere_id' => Filiere::query()->firstOrFail()->id,
            'email'      => 'reg-' . Str::random(6) . '@example.test',
        ], $override);
    }

    public function test_le_matricule_est_obligatoire(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->postJson('/api/admin/students', $this->payload(['matricule' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('matricule');
    }

    public function test_lannee_imposee_est_lannee_active_meme_si_le_client_en_envoie_une_autre(): void
    {
        $active = AnneeAcademique::where('active', true)->firstOrFail();
        $autre  = AnneeAcademique::where('active', false)->first();
        $this->assertNotNull($autre, 'Le test suppose au moins une année inactive.');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->postJson('/api/admin/students', $this->payload(['annee_id' => $autre->id]));

        $response->assertStatus(201);

        $etudiant = Etudiant::where('id', $response->json('data.id'))->firstOrFail();
        $this->assertSame($active->id, $etudiant->annee_id, "L'étudiant doit être rattaché à l'année active.");
    }
}
