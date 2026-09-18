<?php

declare(strict_types=1);

namespace App\Services\Presence;

use App\Models\Etudiant;
use App\Models\Evenement;

/**
 * L'étudiant est-il attendu à cette séance ?
 *
 * Règle partagée avec l'enregistrement manuel d'une présence : voir
 * Etudiant::peutAssisterA().
 */
final class InscriptionAuCours
{
    public static function verifier(Etudiant $etudiant, Evenement $evenement): Verdict
    {
        if (!$etudiant->peutAssisterA($evenement)) {
            return Verdict::refus('Étudiant non inscrit à ce cours.');
        }

        return Verdict::satisfait();
    }
}
