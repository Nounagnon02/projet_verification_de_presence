<?php

namespace App\Services;

use App\Models\Anomaly;
use App\Models\Member;
use App\Models\Presence;
use Carbon\Carbon;

class AnomalyDetectionService
{
    /**
     * Vérifie les anomalies lors d'un pointage
     */
    public function checkAnomalies(Member $member, array $context): void
    {
        $this->checkDeviceAnomaly($member, $context);
        $this->checkLocationAnomaly($member, $context);
        $this->checkTimeAnomaly($member, $context);
    }

    /**
     * Vérifie si l'appareil a été utilisé par d'autres membres récemment
     */
    private function checkDeviceAnomaly(Member $member, array $context): void
    {
        if (empty($context['device_fingerprint'])) {
            return;
        }

        // Chercher si cet appareil a été utilisé par d'autres membres aujourd'hui
        $otherMembersCount = Presence::whereDate('date', today())
            ->where('member_id', '!=', $member->id)
            // On suppose qu'on stocke le fingerprint quelque part, ou on utilise l'IP
            // Pour l'instant on utilise l'IP comme proxy si le fingerprint n'est pas stocké en DB
            ->where('ip_address', $context['ip_address']) 
            ->distinct('member_id')
            ->count();

        if ($otherMembersCount >= 2) { // Si 3ème personne sur le même appareil
            $this->reportAnomaly($member, 'multiple_devices', 'Appareil utilisé par plusieurs membres (' . ($otherMembersCount + 1) . ')', 'high', $context);
        }
    }

    /**
     * Vérifie si la localisation est suspecte (changement rapide)
     */
    private function checkLocationAnomaly(Member $member, array $context): void
    {
        if (empty($context['latitude']) || empty($context['longitude'])) {
            return;
        }

        // Récupérer la dernière présence avec localisation
        $lastPresence = Presence::where('member_id', $member->id)
            ->whereNotNull('location_data')
            ->latest()
            ->first();

        if ($lastPresence && $lastPresence->location_data) {
            // Calculer la vitesse de déplacement nécessaire
            // ... (implémentation simplifiée pour l'instant)
        }
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
