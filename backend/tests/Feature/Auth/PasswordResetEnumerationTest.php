<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * « Mot de passe oublié » — aucun oracle.
 *
 * Régression : la réponse renvoyait « We can't find a user with that email
 * address » (422) pour un email inconnu et un message de succès (200) pour un
 * email existant. L'endpoint permettait ainsi d'énumérer les comptes.
 */
class PasswordResetEnumerationTest extends TestCase
{
    public function test_email_existant_et_email_inconnu_donnent_la_meme_reponse(): void
    {
        Notification::fake();

        $utilisateur = User::factory()->create();

        $reponseConnue = $this->postJson('/api/forgot-password', ['email' => $utilisateur->email]);
        $reponseInconnue = $this->postJson('/api/forgot-password', ['email' => 'personne-' . Str::random(8) . '@example.test']);

        $reponseConnue->assertOk();
        $reponseInconnue->assertOk();
        $this->assertSame($reponseConnue->json('message'), $reponseInconnue->json('message'));
        $this->assertSame($reponseConnue->status(), $reponseInconnue->status());
    }

    public function test_un_compte_existant_recoit_bien_le_lien(): void
    {
        Notification::fake();

        $utilisateur = User::factory()->create();

        $this->postJson('/api/forgot-password', ['email' => $utilisateur->email])->assertOk();

        Notification::assertSentTo($utilisateur, \Illuminate\Auth\Notifications\ResetPassword::class);
    }
}
