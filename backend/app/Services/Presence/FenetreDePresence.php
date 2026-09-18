<?php

declare(strict_types=1);

namespace App\Services\Presence;

use App\Models\Evenement;
use Carbon\Carbon;

/**
 * Fenêtre horaire de prise de présence.
 *
 * Ancrée sur l'heure de FIN du cours (config/presence.php), calculée par le
 * modèle : un scan accepté dès le début de séance permettrait de valider sa
 * présence puis de repartir.
 */
final class FenetreDePresence
{
    public static function verifier(Evenement $evenement, Carbon $maintenant): Verdict
    {
        $ouverture = $evenement->ouvertureScan();
        $fermeture = $evenement->fermetureScan();

        if ($maintenant->lessThan($ouverture)) {
            return Verdict::refus(
                "La prise de présence n'est pas encore ouverte. Elle le sera à partir de "
                . $ouverture->format('H:i') . '.'
            );
        }

        if ($maintenant->greaterThan($fermeture)) {
            return Verdict::refus(
                'La prise de présence est terminée depuis ' . $fermeture->format('H:i') . '.'
            );
        }

        return Verdict::satisfait();
    }
}
