<?php

namespace Tests\Feature\Mail;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * Envoi par l'API Gmail (HTTPS, port 443).
 *
 * Render gratuit bloque le SMTP sortant (25, 465, 587) : smtp.gmail.com est
 * injoignable, et l'abonnement Resend n'est pas disponible. L'API Gmail passe
 * par le port 443, jamais filtré. Ce fichier verrouille le comportement du
 * transport « gmail » : échange du jeton, message MIME envoyé tel quel, et
 * échecs remontés — jamais avalés, sinon un jeton révoqué passerait pour un
 * envoi réussi.
 */
class GmailApiTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'gmail',
            'mail.mailers.gmail' => [
                'transport' => 'gmail',
                'client_id' => 'client-id',
                'client_secret' => 'client-secret',
                'refresh_token' => 'refresh-token',
            ],
            'mail.from.address' => 'presence@gmail.com',
            'mail.from.name' => 'UAC Présences',
        ]);
        Cache::flush();
    }

    private function envoyer(): void
    {
        Mail::raw('Bonjour', fn ($m) => $m->to('etudiant@example.com')->subject('Vos identifiants'));
    }

    public function test_le_message_part_par_l_api_gmail_avec_un_jeton_obtenu_du_refresh_token(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'jeton-acces', 'expires_in' => 3599]),
            'gmail.googleapis.com/*' => Http::response(['id' => 'abc'], 200),
        ]);

        $this->envoyer();

        Http::assertSent(fn ($r) => $r->url() === 'https://oauth2.googleapis.com/token'
            && $r['grant_type'] === 'refresh_token'
            && $r['refresh_token'] === 'refresh-token'
            && $r['client_id'] === 'client-id'
            && $r['client_secret'] === 'client-secret');

        Http::assertSent(function ($r) {
            if ($r->url() !== 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send') {
                return false;
            }
            // base64url (RFC 4648 §5) : ni « + », ni « / », ni « = ».
            $mime = base64_decode(strtr($r['raw'], '-_', '+/'));

            return $r->hasHeader('Authorization', 'Bearer jeton-acces')
                && ! preg_match('/[+\/=]/', $r['raw'])
                && str_contains($mime, 'To: etudiant@example.com')
                && str_contains($mime, 'Subject: Vos identifiants')
                && str_contains($mime, 'presence@gmail.com');
        });
    }

    public function test_le_jeton_d_acces_est_reutilise_entre_deux_envois(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'jeton-acces', 'expires_in' => 3599]),
            'gmail.googleapis.com/*' => Http::response(['id' => 'abc'], 200),
        ]);

        $this->envoyer();
        $this->envoyer();

        Http::assertSentCount(3); // un échange de jeton, deux envois
    }

    public function test_un_refus_de_google_remonte_en_exception_avec_son_motif(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'jeton-acces', 'expires_in' => 3599]),
            'gmail.googleapis.com/*' => Http::response(['error' => ['code' => 403, 'message' => 'Daily user sending quota exceeded']], 403),
        ]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Daily user sending quota exceeded');

        $this->envoyer();
    }

    public function test_un_refresh_token_revoque_remonte_en_exception(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400),
        ]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('invalid_grant');

        $this->envoyer();
    }

    public function test_un_identifiant_manquant_est_signale_sans_appel_reseau(): void
    {
        config(['mail.mailers.gmail.refresh_token' => null]);
        Http::fake();

        try {
            $this->envoyer();
            $this->fail('Une configuration incomplète doit lever une exception.');
        } catch (TransportException $e) {
            $this->assertStringContainsString('GMAIL_REFRESH_TOKEN', $e->getMessage());
        }

        Http::assertNothingSent();
    }
}
