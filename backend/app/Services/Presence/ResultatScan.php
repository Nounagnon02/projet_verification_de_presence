<?php

declare(strict_types=1);

namespace App\Services\Presence;

/**
 * Issue complète d'un scan, telle que le contrôleur n'a plus qu'à la traduire
 * en réponse HTTP.
 *
 * PresenceController::scan() faisait 351 lignes et enchaînait dix vérifications
 * numérotées en commentaire, chacune construisant elle-même sa réponse : aucune
 * ne pouvait être exercée sans monter une requête HTTP complète. Le contrôleur
 * ne connaît désormais que ce type-ci.
 */
final class ResultatScan
{
    /**
     * @param  array<string, mixed>  $donnees  Charge utile de la réponse, vide en cas de refus.
     */
    private function __construct(
        public readonly bool $accepte,
        public readonly int $statutHttp,
        public readonly string $message,
        public readonly array $donnees = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    public static function enregistre(array $donnees, int $statutHttp = 201): self
    {
        return new self(true, $statutHttp, 'Présence enregistrée avec succès.', $donnees);
    }

    public static function refus(string $message, int $statutHttp = 403): self
    {
        return new self(false, $statutHttp, $message);
    }

    /**
     * Refus porté par une vérification : le message est celui destiné à
     * l'étudiant, jamais le détail technique conservé dans l'anomalie.
     */
    public static function depuisVerdict(Verdict $verdict): self
    {
        return self::refus((string) $verdict->messageEtudiant, $verdict->statutHttp);
    }
}
