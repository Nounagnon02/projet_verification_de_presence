<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Presence;
use App\Models\AttendanceSession;

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
