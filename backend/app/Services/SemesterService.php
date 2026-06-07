<?php

namespace App\Services;

use App\Models\Filiere;
use App\Models\Ue;
use Illuminate\Support\Collection;

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

    /**
     * Calcule le taux de présence par semestre pour une année académique et une filière.
     *
     * @param int $anneeId
     * @param int|null $filiereId Optionnel, null = toutes filières
     * @return Collection [{semestre: int, label: string, taux: float, total_presences: int, total_attendus: int}]
     */
    public function tauxParSemestre(int $anneeId, ?int $filiereId = null): Collection
    {
        $query = Ue::selectRaw('
                ues.semestre,
                COUNT(DISTINCT presences.id) as total_presences,
                COUNT(DISTINCT evenements.id) as total_evenements,
                COUNT(DISTINCT etudiant_ec.etudiant_id) as total_etudiants
            ')
            ->join('ecs', 'ecs.ue_id', '=', 'ues.id')
            ->join('evenements', 'evenements.ec_id', '=', 'ecs.id')
            ->leftJoin('presences', 'presences.evenement_id', '=', 'evenements.id')
            ->leftJoin('etudiant_ec', function ($join) use ($anneeId) {
                $join->on('etudiant_ec.ec_id', '=', 'ecs.id')
                    ->on('etudiant_ec.annee_id', '=', \DB::raw((string) $anneeId));
            })
            ->where('ues.annee_id', $anneeId)
            ->groupBy('ues.semestre')
            ->orderBy('ues.semestre');

        if ($filiereId) {
            $query->where('ues.filiere_id', $filiereId);
        }

        $results = $query->get();

        return $results->map(function ($row) {
            $totalAttendus = ($row->total_evenements ?? 0) * ($row->total_etudiants ?? 0);
            return [
                'semestre'         => (int) $row->semestre,
                'label'            => $this->semesterLabel((int) $row->semestre),
                'taux'             => $totalAttendus > 0 ? round(($row->total_presences / $totalAttendus) * 100, 1) : 0,
                'total_presences'  => (int) $row->total_presences,
                'total_attendus'   => $totalAttendus,
                'total_evenements' => (int) $row->total_evenements,
                'total_etudiants'  => (int) $row->total_etudiants,
            ];
        });
    }
}
