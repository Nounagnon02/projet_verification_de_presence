<?php

declare(strict_types=1);

namespace App\Mail\Transport;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Envoie les e-mails par l'API Gmail (HTTPS, port 443).
 *
 * Render gratuit bloque le SMTP sortant : smtp.gmail.com n'est pas joignable.
 * L'API Gmail utilise le port 443 et un jeton OAuth2 obtenu à partir d'un
 * « refresh token » à longue durée. Le message MIME construit par Laravel part
 * tel quel ; Gmail réécrit seulement l'expéditeur avec le compte autorisé.
 *
 * Toute erreur de Google remonte en TransportException, avec son motif : un
 * jeton révoqué ou un quota atteint ne doit jamais passer pour un envoi réussi.
 */
class GmailApiTransport extends AbstractTransport
{
    private const URL_JETON = 'https://oauth2.googleapis.com/token';

    private const URL_ENVOI = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    private const CLE_CACHE = 'gmail_api_access_token';

    public function __construct(
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
        private readonly ?string $refreshToken,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $reponse = $this->requete(
            fn () => Http::withToken($this->jetonAcces())
                ->timeout(20)
                ->asJson()
                ->post(self::URL_ENVOI, ['raw' => $this->base64Url($message->toString())]),
            'envoi',
        );

        if ($reponse->failed()) {
            // Jeton refusé à l'envoi : on l'oublie pour en redemander un au prochain essai.
            if ($reponse->status() === 401) {
                Cache::forget(self::CLE_CACHE);
            }

            throw new TransportException(sprintf(
                "L'API Gmail a refusé l'envoi (HTTP %d) : %s",
                $reponse->status(),
                $reponse->json('error.message') ?? $reponse->body(),
            ));
        }
    }

    public function __toString(): string
    {
        return 'gmail+api://default';
    }

    private function jetonAcces(): string
    {
        $manquants = array_keys(array_filter([
            'GMAIL_CLIENT_ID' => $this->clientId,
            'GMAIL_CLIENT_SECRET' => $this->clientSecret,
            'GMAIL_REFRESH_TOKEN' => $this->refreshToken,
        ], fn ($valeur) => $valeur === null || $valeur === ''));

        if ($manquants !== []) {
            throw new TransportException('Configuration Gmail incomplète : '.implode(', ', $manquants).' manquant.');
        }

        $jeton = Cache::get(self::CLE_CACHE);
        if (is_string($jeton) && $jeton !== '') {
            return $jeton;
        }

        $reponse = $this->requete(
            fn () => Http::timeout(20)->asForm()->post(self::URL_JETON, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]),
            'obtention du jeton',
        );

        $jeton = $reponse->json('access_token');
        if ($reponse->failed() || ! is_string($jeton)) {
            throw new TransportException(sprintf(
                'Google a refusé le refresh token (HTTP %d) : %s %s',
                $reponse->status(),
                $reponse->json('error') ?? '',
                $reponse->json('error_description') ?? '',
            ));
        }

        // Marge de 5 minutes sur la durée de vie annoncée (3600 s en pratique).
        $duree = max(60, (int) $reponse->json('expires_in', 3600) - 300);
        Cache::put(self::CLE_CACHE, $jeton, $duree);

        return $jeton;
    }

    /** @param callable():Response $appel */
    private function requete(callable $appel, string $etape): Response
    {
        try {
            return $appel();
        } catch (ConnectionException $e) {
            throw new TransportException("API Gmail injoignable ({$etape}) : ".$e->getMessage(), 0, $e);
        }
    }

    /** base64url (RFC 4648 §5), sans remplissage : le format attendu par Gmail. */
    private function base64Url(string $contenu): string
    {
        return rtrim(strtr(base64_encode($contenu), '+/', '-_'), '=');
    }
}
