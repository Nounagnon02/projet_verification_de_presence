<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Evenement;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventAndYearRulesTest extends TestCase
{
    private function token(): string
    {
        return User::factory()->create([
            'email' => 'evt-' . Str::random(6) . '@example.test',
            'role'  => 'super_admin',
        ])->createToken('t')->plainTextToken;
    }

    public function test_evenement_deduit_filiere_et_annee_de_lec(): void
    {
        // Un EC rattaché à une UE (donc à une filière + année précises).
        $ue = Ue::query()->whereNotNull('filiere_id')->whereNotNull('annee_id')->firstOrFail();
        $ec = Ec::where('ue_id', $ue->id)->where('statut', '!=', 'termine')->first()
            ?? Ec::create(['ue_id' => $ue->id, 'code' => 'EVT' . Str::random(4), 'intitule' => 'Test', 'volume_horaire' => 20, 'statut' => 'planifie']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->postJson('/api/admin/evenements', [
                'ec_id'       => $ec->id,
                'date'        => now()->addDay()->toDateString(),
                'heure_debut' => '08:00',
                'heure_fin'   => '10:00',
                // On envoie volontairement des valeurs incohérentes : elles
                // doivent être ignorées au profit de celles déduites de l'EC.
                'filiere_id'  => 99999,
                'annee_id'    => 99999,
            ]);

        $response->assertStatus(201);
        $evenement = Evenement::findOrFail($response->json('data.id'));
        $this->assertSame($ue->filiere_id, $evenement->filiere_id);
        $this->assertSame($ue->annee_id, $evenement->annee_id);
    }

    public function test_impossible_de_desactiver_lannee_active(): void
    {
        $active = AnneeAcademique::where('active', true)->firstOrFail();

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/admin/annees-academiques/' . $active->id, ['active' => false])
            ->assertStatus(409);

        $this->assertTrue($active->fresh()->active);
    }

    public function test_impossible_de_supprimer_lannee_active(): void
    {
        $active = AnneeAcademique::where('active', true)->firstOrFail();

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->deleteJson('/api/admin/annees-academiques/' . $active->id)
            ->assertStatus(409);

        $this->assertNotNull($active->fresh());
    }
}
