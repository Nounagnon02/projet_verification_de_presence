<?php

namespace App\Services;

use App\Models\Anomaly;
use App\Models\Member;
use Carbon\Carbon;

class AnomalyDetectionService
{
    /**
     * Vérifie les anomalies lors d'un pointage.
     *
     * Les anciens contrôles "appareil partagé" et "localisation" reposaient sur
     * des colonnes de presences.ip_address/location_data supprimées (déménagées
     * au niveau de la session) et sur l'hypothèse d'un scan par le membre
     * lui-même. Depuis que c'est le responsable qui scanne chaque membre avec
     * son propre appareil, un appareil partagé par plusieurs membres est le
     * comportement normal, pas une anomalie — ces deux contrôles sont retirés.
     */
    public function checkAnomalies(Member $member, array $context): void
    {
        $this->checkTimeAnomaly($member, $context);
    }

    /**
     * Vérifie si l'heure est inhabituelle
     */
    private function checkTimeAnomaly(Member $member, array $context): void
    {
        $hour = Carbon::now()->hour;
        
        // Si pointage entre 22h et 5h du matin
        if ($hour >= 22 || $hour < 5) {
            $this->reportAnomaly($member, 'unusual_time', 'Pointage à une heure inhabituelle (' . $hour . 'h)', 'low', $context);
        }
    }

    /**
     * Signale une anomalie
     */
    private function reportAnomaly(Member $member, string $type, string $description, string $severity, array $metadata): void
    {
        Anomaly::create([
            'member_id' => $member->id,
            'type' => $type,
            'description' => $description,
            'severity' => $severity,
            'metadata' => $metadata
        ]);
    }
}
