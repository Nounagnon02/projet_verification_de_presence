<?php

namespace App\Services;

use App\Models\Filiere;

/**
 * Service de gestion des semestres académiques (CDC 7.1).
 *
 * Chaque niveau (niveau) correspond à une plage de deux semestres :
 *   L1 → S1, S2
 *   L2 → S3, S4
 *   L3 → S5, S6
 *   M1 → S7, S8
 *   M2 → S9, S10
 */
class SemesterService
{
    /**
     * Mapping niveau → [semestres]
     */
    public const MAPPING = [
        'L1' => [1, 2],
        'L2' => [3, 4],
        'L3' => [5, 6],
        'M1' => [7, 8],
        'M2' => [9, 10],
    ];

    /**
     * Libellés des niveaux, dans l'ordre du cursus. Avec MAPPING, c'est la
     * seule liste des niveaux : le serveur la valide, les écrans la lisent par
     * GET /admin/niveaux au lieu de la recopier.
     */
    public const LIBELLES = [
        'L1' => 'Licence 1',
        'L2' => 'Licence 2',
        'L3' => 'Licence 3',
        'M1' => 'Master 1',
        'M2' => 'Master 2',
    ];

    /** @return list<string> les niveaux, dans l'ordre du cursus */
    public function niveaux(): array
    {
        return array_keys(self::MAPPING);
    }

    /**
     * Retourne la liste des semestres pour un niveau donné.
     *
     * @param string $niveau (L1, L2, L3, M1, M2)
     * @return int[]
     */
    public function getSemestersForNiveau(string $niveau): array
    {
        return self::MAPPING[strtoupper($niveau)] ?? [];
    }

    /**
     * Retourne les numéros de semestre pour une filière.
     *
     * @param Filiere|int $filiere
     * @return int[]
     */
    public function getSemestersForFiliere(Filiere|int $filiere): array
    {
        if ($filiere instanceof Filiere) {
            $niveau = $filiere->niveau;
        } else {
            $filiere = Filiere::find($filiere);
            $niveau = $filiere?->niveau;
        }

        return $niveau ? $this->getSemestersForNiveau($niveau) : [];
    }

    /**
     * Retourne le libellé court d'un semestre (ex: 1 → "S1", 2 → "S2").
     */
    public function semesterLabel(int $semestre): string
    {
        return "S{$semestre}";
    }

    /**
     * Retourne le niveau correspondant à un semestre.
     * Ex: 1 ou 2 → L1, 3 ou 4 → L2.
     */
    public function getNiveauForSemestre(int $semestre): ?string
    {
        foreach (self::MAPPING as $niveau => $range) {
            if (in_array($semestre, $range, true)) {
                return $niveau;
            }
        }
        return null;
    }

    /**
     * Retourne tous les semestres valides (1 à 10).
     */
    public function allSemesters(): array
    {
        return [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
    }
}
