<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Member;
use App\Models\Presence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'export des données d'un membre matérialise les droits d'accès et de
 * portabilité du RGPD. C'est une sortie de données personnelles : le cloisonnement
 * entre responsables doit rester vérifié automatiquement.
 */
class MemberDataExportTest extends TestCase
{
    use RefreshDatabase;

    private function responsableAvecMembre(string $email): array
    {
        $user = User::factory()->create(['email' => $email]);
        $group = Group::create(['name' => 'Groupe de '.$email]);
        $group->leaders()->attach($user->id);

        $member = Member::create([
            'name' => 'Membre de '.$email,
            'phone' => (string) random_int(10000000, 99999999),
            'users_id' => $user->id,
            'rgpd_consent' => true,
            'rgpd_consent_at' => now(),
            'consent_method' => 'oral',
        ]);
        $member->groups()->attach($group->id);

        return [$user, $member];
    }

    public function test_un_responsable_exporte_les_donnees_de_son_membre(): void
    {
        [$user, $member] = $this->responsableAvecMembre('proprietaire@example.test');

        Presence::create([
            'member_id' => $member->id,
            'date' => '2026-09-20',
            'time' => '09:15:00',
        ]);

        $response = $this->actingAs($user)->get(route('membres.export', $member));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');

        $donnees = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($member->name, $donnees['membre']['nom']);
        $this->assertTrue($donnees['consentement']['accorde']);
        $this->assertCount(1, $donnees['presences']);
        $this->assertSame('2026-09-20', $donnees['presences'][0]['date']);
    }

    public function test_un_responsable_ne_peut_pas_exporter_le_membre_d_un_autre(): void
    {
        [$intrus] = $this->responsableAvecMembre('intrus@example.test');
        [, $membreAutrui] = $this->responsableAvecMembre('proprietaire@example.test');

        $this->actingAs($intrus)
            ->get(route('membres.export', $membreAutrui))
            ->assertForbidden();
    }

    public function test_un_visiteur_non_connecte_est_renvoye_vers_la_connexion(): void
    {
        [, $member] = $this->responsableAvecMembre('proprietaire@example.test');

        $this->get(route('membres.export', $member))->assertRedirect(route('login'));
    }
}
