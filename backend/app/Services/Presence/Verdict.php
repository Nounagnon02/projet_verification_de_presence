<?php

declare(strict_types=1);

namespace App\Services\Presence;

/**
 * Résultat d'UNE vérification de scan.
 *
 * Deux destinataires, et c'est toute la raison d'être de cette classe :
 *
 *   - « messageEtudiant » est lu par l'étudiant. Il reste générique. Le refus
 *     annonçait auparavant la distance exacte qui le séparait de la salle, le
 *     rayon autorisé et le SSID attendu : trois scans depuis trois endroits
 *     suffisaient à trianguler la position de la salle, et le nom du réseau
 *     était donné à qui ne l'avait pas.
 *   - « detail » n'est lu que par l'administration, via l'anomalie enregistrée.
 *     C'est là, et seulement là, que figurent la distance, le rayon et les
 *     réseaux comparés.
 */
final class Verdict
{
    /**
     * @param  array<string, mixed>  $detail
     */
    private function __construct(
        public readonly bool $satisfait,
        public readonly ?string $messageEtudiant,
        public readonly int $statutHttp,
        public readonly array $detail,
    ) {
    }

    /**
     * @param  array<string, mixed>  $detail  Ce qui sera journalisé même en cas de succès.
     */
    public static function satisfait(array $detail = []): self
    {
        return new self(true, null, 200, $detail);
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    public static function refus(string $messageEtudiant, int $statutHttp = 403, array $detail = []): self
    {
        return new self(false, $messageEtudiant, $statutHttp, $detail);
    }
}
