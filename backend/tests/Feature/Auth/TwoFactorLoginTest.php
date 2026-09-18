<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * La 2FA, une fois activée, est réellement exigée au login : le mot de passe
 * seul ne délivre plus de token.
 */
class TwoFactorLoginTest extends TestCase
{
    private string $secret;
    private string $email;
    private string $password = 'motdepasse123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->secret = (new Google2FA())->generateSecretKey();
        $this->email = 'tfa-' . Str::random(6) . '@x.test';

        User::factory()->create([
            'email'                     => $this->email,
            'password'                  => $this->password,
            'role'                      => 'super_admin',
            'must_change_password'      => false,
            'two_factor_secret'         => $this->secret,
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => json_encode(['RECOVER-AAA', 'RECOVER-BBB']),
        ]);
    }

    public function test_mot_de_passe_seul_exige_le_2fa_sans_token(): void
    {
        $r = $this->postJson('/api/login', ['email' => $this->email, 'password' => $this->password]);
        $r->assertStatus(200)
          ->assertJsonPath('two_factor_required', true);
        $this->assertNull($r->json('data.token'));
    }

    public function test_code_totp_invalide_refuse(): void
    {
        $this->postJson('/api/login', ['email' => $this->email, 'password' => $this->password, 'code' => '000000'])
            ->assertStatus(422);
    }

    public function test_code_totp_valide_delivre_le_token(): void
    {
        $otp = (new Google2FA())->getCurrentOtp($this->secret);
        $r = $this->postJson('/api/login', ['email' => $this->email, 'password' => $this->password, 'code' => $otp]);
        $r->assertStatus(200)->assertJsonPath('success', true);
        $this->assertNotNull($r->json('data.token'));
    }

    public function test_code_de_recuperation_fonctionne_et_est_consomme(): void
    {
        $r = $this->postJson('/api/login', ['email' => $this->email, 'password' => $this->password, 'code' => 'RECOVER-AAA']);
        $r->assertStatus(200);
        $this->assertNotNull($r->json('data.token'));

        // Le code de récupération est consommé (usage unique) : il ne remarche pas.
        $user = User::where('email', $this->email)->first();
        $codes = json_decode($user->two_factor_recovery_codes, true);
        $this->assertNotContains('RECOVER-AAA', $codes);
    }

    /**
     * Régression : Auth::attempt() journalisait l'utilisateur (cookie de
     * session) dès le mot de passe validé, AVANT même de savoir qu'un second
     * facteur était requis. Combiné au mode Sanctum « stateful », un appelant
     * se déclarant Origin: http://localhost obtenait une session valide sans
     * jamais fournir le code TOTP.
     */
    public function test_le_mot_de_passe_seul_n_ouvre_aucune_session_avant_le_2fa(): void
    {
        $reponse = $this->withHeaders(['Origin' => 'http://localhost'])
            ->postJson('/api/login', ['email' => $this->email, 'password' => $this->password]);

        $reponse->assertStatus(200)->assertJsonPath('two_factor_required', true);

        // Aucune session : ni le guard par défaut, ni un cookie de session
        // dans la réponse. Avant correction, ce test échouait sur les deux.
        $this->assertGuest();
        $enTeteCookies = $reponse->headers->get('Set-Cookie', '');
        $this->assertStringNotContainsString((string) config('session.cookie'), (string) $enTeteCookies);
    }

    public function test_un_code_totp_invalide_n_ouvre_pas_davantage_de_session(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost'])
            ->postJson('/api/login', ['email' => $this->email, 'password' => $this->password, 'code' => '000000'])
            ->assertStatus(422);

        $this->assertGuest();
    }
}
