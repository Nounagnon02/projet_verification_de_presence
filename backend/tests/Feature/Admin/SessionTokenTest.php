<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * La gestion des sessions s'appuie sur les tokens Sanctum : « déconnecter les
 * autres appareils » révoque réellement les autres tokens.
 */
class SessionTokenTest extends TestCase
{
    public function test_liste_les_tokens_et_marque_le_courant(): void
    {
        $u = User::factory()->create(['email' => 'sess-' . Str::random(6) . '@x.test', 'role' => 'super_admin', 'must_change_password' => false]);
        $u->createToken('ancien-appareil');
        $courant = $u->createToken('appareil-courant')->plainTextToken;

        $r = $this->withHeader('Authorization', 'Bearer ' . $courant)->getJson('/api/admin/sessions');
        $r->assertStatus(200);
        $data = collect($r->json('data'));
        $this->assertSame(2, $data->count());
        $this->assertSame(1, $data->where('is_current', true)->count());
    }

    public function test_destroy_others_revoque_les_autres_tokens(): void
    {
        $u = User::factory()->create(['email' => 'sess2-' . Str::random(6) . '@x.test', 'role' => 'super_admin', 'must_change_password' => false]);
        $autre = $u->createToken('autre')->plainTextToken;
        $courant = $u->createToken('courant')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $courant)
            ->deleteJson('/api/admin/sessions/others')->assertStatus(200);

        // Le token courant marche encore, l'autre est révoqué.
        $this->withHeader('Authorization', 'Bearer ' . $courant)->getJson('/api/admin/sessions')->assertStatus(200);

        // Indispensable : le garde d'authentification mémorise l'utilisateur
        // résolu, et le conserve d'une requête à l'autre au sein d'un même test.
        // Sans cette purge, la requête suivante réutilise l'utilisateur déjà
        // authentifié et répond 200 quel que soit le token présenté — le test
        // semblait alors révéler une faille de révocation qui n'existe pas.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer ' . $autre)->getJson('/api/admin/sessions')->assertStatus(401);
    }
}
