<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Tests\TestCase;

/**
 * Limitation de débit dédiée à la vérification du code TOTP.
 *
 * Régression : confirm2FA()/verify2FA() n'étaient couverts que par le
 * throttle général de l'API (60 requêtes/minute) — un espace de 10^6 codes se
 * teste largement dans cette marge.
 */
class TotpRateLimitTest extends TestCase
{
    public function test_cinq_codes_invalides_bloquent_le_sixieme_essai(): void
    {
        $user = User::factory()->create(['two_factor_secret' => 'JBSWY3DPEHPK3PXP']);
        $jeton = $user->createToken('t')->plainTextToken;

        for ($i = 0; $i < 5; $i++) {
            $this->withToken($jeton)
                ->postJson('/api/admin/profile/2fa/confirm', ['code' => '000000'])
                ->assertStatus(422);
        }

        $this->withToken($jeton)
            ->postJson('/api/admin/profile/2fa/confirm', ['code' => '000000'])
            ->assertStatus(429);
    }
}
