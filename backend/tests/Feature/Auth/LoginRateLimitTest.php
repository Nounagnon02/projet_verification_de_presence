<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Limitation de débit du login admin (CDC 9.1) — double clé, comme le login
 * étudiant.
 *
 * Régression : la clé unique était l'email fourni par l'appelant. En faisant
 * varier l'email essayé (password spraying), un mot de passe se testait
 * contre des milliers de comptes sans jamais être ralenti par IP.
 */
class LoginRateLimitTest extends TestCase
{
    public function test_cinq_echecs_sur_le_meme_email_bloquent_le_sixieme(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'faux'])
                ->assertStatus(422);
        }

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'faux'])
            ->assertStatus(429);
    }

    public function test_faire_varier_l_email_ne_contourne_pas_la_limite_par_ip(): void
    {
        // Password spraying : des e-mails distincts, tous jamais essayés
        // auparavant. La clé par IP (limite de 20/min) doit à elle seule finir
        // par bloquer, même si aucun email individuel n'atteint sa propre
        // limite de 5. Vingt et un essais : la limite de 20 autorise le
        // vingtième et refuse le suivant.
        for ($i = 0; $i < 21; $i++) {
            $reponse = $this->postJson('/api/login', [
                'email'    => 'spray-' . Str::random(10) . '@example.test',
                'password' => 'faux',
            ]);

            if ($reponse->status() === 429) {
                $this->assertSame(21, $i + 1, 'La limite par IP doit se déclencher au vingt-et-unième essai.');

                return;
            }
        }

        $this->fail("Vingt-et-un essais avec des e-mails distincts depuis la même IP n'ont jamais été bloqués.");
    }
}
