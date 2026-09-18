<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Etudiant;
use Illuminate\Support\Facades\Hash;

/**
 * Tirage et attribution du code d'accès d'un étudiant.
 *
 * Ce code est le seul secret de la connexion étudiante : l'email et
 * l'identifiant unique sont tous deux déterministes (voir IdentifiantService),
 * donc reconstituables par un camarade de promotion.
 *
 * Deux règles tiennent tout le reste :
 *   - le code en clair n'existe que le temps de l'e-mail qui le transporte ;
 *     la base ne conserve que son hachage ;
 *   - il n'est jamais retourné par une API ni écrit dans un journal. Une
 *     réponse HTTP qui le contiendrait le rendrait lisible dans les journaux
 *     du répartiteur, et le rendrait donc public.
 */
class CodeAccesEtudiant
{
    /** Longueur imposée par la saisie mobile : six chiffres, pavé numérique. */
    public const LONGUEUR = 6;

    /**
     * Tire un code à six chiffres.
     *
     * random_int et non rand() : le générateur doit être cryptographique, sans
     * quoi les codes d'une même minute sont prédictibles à partir d'un seul.
     * Le pavage à gauche conserve les codes commençant par zéro, qui seraient
     * sinon écartés — un dixième de l'espace de tirage perdu.
     */
    public static function tirer(): string
    {
        return str_pad((string) random_int(0, 999999), self::LONGUEUR, '0', STR_PAD_LEFT);
    }

    /**
     * Attribue un code neuf à l'étudiant et renvoie sa valeur en clair, à
     * l'usage exclusif de l'e-mail d'identifiants.
     *
     * L'appelant ne doit ni la journaliser ni la renvoyer au client.
     */
    public static function attribuer(Etudiant $etudiant): string
    {
        $code = self::tirer();

        $etudiant->forceFill(['code_acces' => Hash::make($code)])->save();

        return $code;
    }
}
