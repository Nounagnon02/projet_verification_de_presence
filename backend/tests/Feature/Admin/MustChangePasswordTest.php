<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tant que must_change_password est vrai, l'accès aux routes admin est bloqué
 * (sauf le changement de mot de passe), et débloqué une fois le flag levé.
 */
class MustChangePasswordTest extends TestCase
{
    private function tokenAvecFlag(bool $flag): string
    {
        return User::factory()->create([
            'email' => 'mcp-' . Str::random(5) . '@x.test',
            'role'  => 'super_admin',
            'must_change_password' => $flag,
        ])->createToken('t')->plainTextToken;
    }

    public function test_acces_bloque_si_changement_requis(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenAvecFlag(true))
            ->getJson('/api/admin/profile')
            ->assertStatus(403)
            ->assertJsonPath('must_change_password', true);
    }

    public function test_route_de_changement_reste_accessible(): void
    {
        // Non bloquée par le middleware (pas de 403) — la validation du
        // mot de passe actuel peut renvoyer 422, mais surtout PAS 403.
        $r = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAvecFlag(true))
            ->putJson('/api/admin/profile/password', []);
        $this->assertNotSame(403, $r->status());
    }

    public function test_acces_ouvert_si_pas_de_flag(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenAvecFlag(false))
            ->getJson('/api/admin/profile')
            ->assertStatus(200);
    }
}
