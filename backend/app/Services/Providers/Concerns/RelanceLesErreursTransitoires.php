<?php

declare(strict_types=1);

namespace App\Services\Providers\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * Relance en cas d'erreur transitoire (429, 5xx, timeout de connexion), avec
 * un délai qui double à chaque tentative. Gemini seul avait ce comportement ;
 * Groq et OpenRouter abandonnaient l'analyse entière à la première erreur de
 * ce type, alors qu'un simple quota momentané suffisait à la déclencher.
 *
 * Une erreur définitive (4xx hors 429, JSON invalide, etc.) n'entre jamais
 * ici : la relancer ne changerait rien et coûterait des appels facturés pour
 * le même résultat.
 */
trait RelanceLesErreursTransitoires
{
    /**
     * @param  \Closure(): Response  $tentative  Une seule tentative d'appel HTTP.
     * @return array{reponse: ?Response, erreur: ?string}
     */
    private function appelerAvecRelance(\Closure $tentative, int $maxRetries, string $nomFournisseur): array
    {
        $attempt = 0;
        $delayMs = 1000;

        while (true) {
            try {
                $response = $tentative();
            } catch (ConnectionException $e) {
                if ($attempt >= $maxRetries) {
                    return ['reponse' => null, 'erreur' => "Timeout de l'API {$nomFournisseur} après " . ($maxRetries + 1) . ' tentative(s).'];
                }
                $attempt++;
                usleep($delayMs * 1000);
                $delayMs *= 2;
                continue;
            }

            if ($response->successful()) {
                return ['reponse' => $response, 'erreur' => null];
            }

            $status = $response->status();
            $transitoire = $status === 429 || $status >= 500;

            if (!$transitoire || $attempt >= $maxRetries) {
                $motif = $status === 429 ? 'Quota dépassé.' : ($status >= 500 ? 'Erreur serveur.' : $response->body());

                return ['reponse' => null, 'erreur' => "Erreur API {$nomFournisseur} ({$status}) : {$motif}"];
            }

            $attempt++;
            Log::warning("{$nomFournisseur} : erreur {$status}, tentative {$attempt}/{$maxRetries}");
            usleep($delayMs * 1000);
            $delayMs *= 2;
        }
    }
}
