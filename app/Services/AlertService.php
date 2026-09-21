<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Presence;
use App\Models\AttendanceSession;
use App\Models\AlertSetting;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AlertService
{
    /**
     * Vérifie les absences et envoie des alertes
     */
    public function checkAndSendAbsenceAlerts(int $groupId, ?string $eventDate = null): array
    {
        $eventDate = $eventDate ?? today()->format('Y-m-d');
        $alertsSent = [];

        // Récupérer les paramètres d'alerte
        $settings = AlertSetting::where('group_id', $groupId)->first();

        if (!$settings || !$settings->is_active) {
            return ['status' => 'disabled', 'alerts_sent' => 0];
        }

        // Vérifier si une session existe pour aujourd'hui
        $event = AttendanceSession::where('group_id', $groupId)
            ->where('event_date', $eventDate)
            ->first();

        if (!$event) {
            return ['status' => 'no_event', 'alerts_sent' => 0];
        }

        // Récupérer les membres absents
        $absentMembers = $this->getAbsentMembers($groupId, $eventDate);

        foreach ($absentMembers as $member) {
            if ($this->shouldSendAlert($member, $settings)) {
                $result = $this->sendAbsenceAlert($member, $event, $settings);
                if ($result['success']) {
                    $alertsSent[] = $member->name;
                }
            }
        }

        return [
            'status' => 'processed',
            'alerts_sent' => count($alertsSent),
            'members_alerted' => $alertsSent
        ];
    }

    /**
     * Récupère les membres absents pour une date, pour un groupe
     */
    public function getAbsentMembers(int $groupId, string $date): \Illuminate\Support\Collection
    {
        $presentMemberIds = Presence::where('date', $date)
            ->pluck('member_id')
            ->toArray();

        return Member::whereHas('groups', fn ($q) => $q->where('groups.id', $groupId))
            ->whereNotIn('id', $presentMemberIds)
            ->get();
    }

    /**
     * Vérifie si une alerte doit être envoyée
     */
    private function shouldSendAlert(Member $member, AlertSetting $settings): bool
    {
        // Vérifier si le membre a un numéro de téléphone
        if (empty($member->phone)) {
            return false;
        }

        // Vérifier l'heure limite pour les alertes
        if ($settings->alert_after_minutes) {
            $eventStart = Carbon::parse($settings->event_start_time ?? '09:00');
            $alertTime = $eventStart->addMinutes($settings->alert_after_minutes);

            if (now()->lt($alertTime)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Envoie une alerte d'absence
     */
    private function sendAbsenceAlert(Member $member, AttendanceSession $event, AlertSetting $settings): array
    {
        $message = $this->buildAlertMessage($member, $event, $settings);

        try {
            Log::info("Envoi alerte absence à {$member->phone}: {$message}");

            $this->logAlert($member, $event, 'absence_alert');

            return ['success' => true, 'message' => $message];
        } catch (\Exception $e) {
            Log::error("Erreur envoi alerte: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Construit le message d'alerte
     */
    private function buildAlertMessage(Member $member, AttendanceSession $event, AlertSetting $settings): string
    {
        $template = $settings->alert_message_template ??
            "Bonjour {name}, vous n'êtes pas encore enregistré pour l'événement du {date}. N'oubliez pas de pointer !";

        return str_replace(
            ['{name}', '{date}', '{event}'],
            [$member->name, $event->event_date->format('d/m/Y'), $event->event_name ?? 'la séance'],
            $template
        );
    }

    /**
     * Envoie un rappel de pointage
     */
    public function sendReminder(Member $member, string $eventName, string $eventDate): array
    {
        $message = "📢 Rappel: N'oubliez pas l'événement '{$eventName}' prévu le {$eventDate}. Pensez à pointer votre présence !";

        try {
            Log::info("Envoi rappel à {$member->phone}: {$message}");

            return ['success' => true, 'message' => 'Rappel envoyé'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Enregistre une alerte dans les logs
     */
    private function logAlert(Member $member, AttendanceSession $event, string $type): void
    {
        Log::channel('daily')->info("Alerte {$type}", [
            'member_id' => $member->id,
            'member_name' => $member->name,
            'event_date' => $event->event_date,
            'sent_at' => now()
        ]);
    }

    /**
     * Récupère les statistiques d'alertes pour un groupe
     */
    public function getAlertStats(int $groupId, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $totalEvents = AttendanceSession::where('group_id', $groupId)
            ->where('event_date', '>=', $startDate)
            ->count();

        $avgPresenceRate = $this->calculateAveragePresenceRate($groupId, $days);

        return [
            'total_events' => $totalEvents,
            'avg_presence_rate' => $avgPresenceRate,
            'period_days' => $days
        ];
    }

    /**
     * Calcule le taux de présence moyen pour un groupe
     */
    private function calculateAveragePresenceRate(int $groupId, int $days): float
    {
        $totalMembers = Member::whereHas('groups', fn ($q) => $q->where('groups.id', $groupId))->count();

        if ($totalMembers === 0) {
            return 0;
        }

        $totalEvents = AttendanceSession::where('group_id', $groupId)
            ->where('event_date', '>=', now()->subDays($days))
            ->distinct('event_date')
            ->count('event_date');

        $totalPresences = Presence::whereHas('member.groups', fn ($q) => $q->where('groups.id', $groupId))
            ->where('date', '>=', now()->subDays($days))
            ->count();

        $expectedPresences = $totalMembers * $totalEvents;

        return $expectedPresences > 0
            ? round(($totalPresences / $expectedPresences) * 100, 1)
            : 0;
    }
}
