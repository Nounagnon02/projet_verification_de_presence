<?php

namespace App\Services;

use App\Models\Filiere;
use App\Models\AnneeAcademique;

class IdentifiantService
{
    /**
     * Génère un identifiant unique déterministe selon le CDC 7.1.3
     * Format: [NOM]_[PRENOM]_[MATRICULE]_[CODE_FILIERE]_[ANNEE_ACADEMIQUE]
     * Exemple: AGOSSOU_MARC_2024001_IM-L2_2025-2026
     */
    public static function generate(string $nom, string $prenom, string $matricule, int $filiereId, int $anneeId): string
    {
        $filiere = Filiere::findOrFail($filiereId);
        $annee = AnneeAcademique::findOrFail($anneeId);

        // Normalisation : majuscules, suppression accents, remplacement espaces par _
        $nomNorm = self::normalize($nom);
        $prenomNorm = self::normalize($prenom);
        $matriculeNorm = self::normalize($matricule);
        $filiereCode = self::normalize($filiere->code);
        $anneeLibelle = self::normalize($annee->libelle);

        return "{$nomNorm}_{$prenomNorm}_{$matriculeNorm}_{$filiereCode}_{$anneeLibelle}";
    }

    /**
     * Normalise une chaîne : majuscules, sans accents, espaces -> _
     */
    private static function normalize(string $str): string
    {
        // Conversion en majuscules
        $str = mb_strtoupper($str, 'UTF-8');
        
        // Suppression des accents (translittération ASCII)
        $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        
        // Remplacement des espaces et tirets par underscore
        $str = preg_replace('/[\s\-]+/', '_', $str);
        
        // Suppression des caractères non alphanumériques sauf underscore
        $str = preg_replace('/[^A-Z0-9_]/', '', $str);
        
        return $str;
    }

    /**
     * Valide le format d'un identifiant unique
     */
    public static function validate(string $identifiant): bool
    {
        // Format attendu: TEXTE_TEXTE_TEXTE_TEXTE_TEXTE
        return preg_match('/^[A-Z0-9_]+_[A-Z0-9_]+_[A-Z0-9_]+_[A-Z0-9_]+_[0-9]{4}_[0-9]{4}$/', $identifiant) === 1;
    }
}
