<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Types de séance : cours magistral, travaux dirigés, travaux pratiques,
 * évaluation.
 *
 * Les fiches les écrivent de vingt façons (« CM », « Cours », « Travaux
 * dirigés », « TD »…) : une seule normalisation, partagée par les imports, les
 * formulaires et la génération. Une évaluation ne consomme aucun volume : elle
 * n'est ni du CM, ni du TD, ni du TP.
 */
final class TypeCours
{
    public const CM = 'cm';
    public const TD = 'td';
    public const TP = 'tp';
    public const EVALUATION = 'evaluation';

    public const TYPES = [self::CM, self::TD, self::TP, self::EVALUATION];

    public const LIBELLES = [
        self::CM         => 'CM',
        self::TD         => 'TD',
        self::TP         => 'TP',
        self::EVALUATION => 'évaluation',
    ];

    public static function normaliser(?string $brut): string
    {
        $clef = mb_strtolower(trim(Str::ascii((string) $brut)));

        return match (true) {
            $clef === ''                                                                  => self::CM,
            in_array($clef, self::TYPES, true)                                            => $clef,
            str_contains($clef, 'travaux pratiques'), preg_match('/\btp\b/', $clef) === 1 => self::TP,
            str_contains($clef, 'travaux diriges'), preg_match('/\btd\b/', $clef) === 1   => self::TD,
            str_contains($clef, 'evaluation'), str_contains($clef, 'examen'),
            str_contains($clef, 'controle')                                               => self::EVALUATION,
            default                                                                       => self::CM,
        };
    }
}
