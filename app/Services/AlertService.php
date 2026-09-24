<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Presence;
use App\Models\AttendanceSession;
use Illuminate\Support\Facades\Log;

class AlertService
{
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
     * Envoie un rappel de pointage
     */
    public function sendReminder(Member $member, string $eventName, string $eventDate): array
    {
        $message = __("Rappel : n'oubliez pas l'événement « :event » prévu le :date. Pensez à pointer votre présence.", [
            'event' => $eventName,
            'date' => $eventDate,
        ]);

        try {
            Log::info("Envoi rappel à {$member->phone}: {$message}");

            return ['success' => true, 'message' => __('Rappel envoyé.')];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
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
