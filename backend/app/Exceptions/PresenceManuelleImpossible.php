<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Refus d'enregistrer une présence à la main. Le message s'adresse à
 * l'administrateur ; le code HTTP dit s'il s'agit d'une règle non respectée
 * (422) ou d'une présence déjà acquise (409).
 */
class PresenceManuelleImpossible extends RuntimeException
{
    public function __construct(string $message, public readonly int $statutHttp = 422)
    {
        parent::__construct($message);
    }
}
