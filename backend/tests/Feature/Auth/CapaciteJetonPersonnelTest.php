<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Un jeton du personnel (connexion par mot de passe) ne doit pas franchir la
 * route de scan, réservée aux jetons de capacité « etudiant ». Sans capacité
 * explicite, Sanctum délivre « * », qui satisfait n'importe quel contrôle.
 */
class CapaciteJetonPersonnelTest extends TestCase
{
    private function connecter(): string
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $jeton = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->json('data.token');

        // La connexion ouvre aussi une session, que Sanctum traite comme un jeton
        // transitoire aux capacités illimitées : un client mobile n'a que le jeton.
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return $jeton;
    }

    public function test_le_jeton_de_connexion_ne_porte_que_la_capacite_admin(): void
    {
        $jeton = PersonalAccessToken::findToken($this->connecter());

        $this->assertSame(['admin'], $jeton->abilities);
        $this->assertFalse($jeton->can('etudiant'));
    }

    public function test_un_jeton_du_personnel_est_refuse_sur_la_route_de_scan(): void
    {
        $this->withToken($this->connecter())
            ->postJson('/api/presence/scan', [])
            ->assertForbidden();
    }

    public function test_un_compte_du_personnel_connecte_par_session_est_refuse_sur_la_route_de_scan(): void
    {
        // Session et non jeton : Sanctum accorde alors toutes les capacités.
        $this->actingAs(User::factory()->create())
            ->postJson('/api/presence/scan', [])
            ->assertForbidden();
    }
}
