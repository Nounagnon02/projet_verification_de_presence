<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\Badge;
use App\Models\Member;
use App\Models\Presence;
use Carbon\Carbon;

class BadgeService
{
    /**
     * Vérifie et attribue les badges pour un membre
     */
    public function checkAndAwardBadges(Member $member): array
    {
        $newBadges = [];
        $badges = Badge::active()->get();

        foreach ($badges as $badge) {
            // Vérifier si le membre n'a pas déjà ce badge
            if (!$member->badges->contains($badge->id)) {
                if ($this->checkCondition($member, $badge)) {
                    $this->awardBadge($member, $badge);
                    $newBadges[] = $badge;
                }
            }
        }

        return $newBadges;
    }

    /**
     * Vérifie si un membre remplit la condition pour un badge
     */
    private function checkCondition(Member $member, Badge $badge): bool
    {
        return match ($badge->condition) {
            'streak_7' => $this->checkStreak($member, 7),
            'streak_14' => $this->checkStreak($member, 14),
            'streak_30' => $this->checkStreak($member, 30),
            'perfect_month' => $this->checkPerfectMonth($member),
            'early_bird' => $this->checkEarlyBird($member, $badge->threshold),
            'first_presence' => $this->checkFirstPresence($member),
            'regular_10' => $this->checkRegularPresences($member, 10),
            'regular_25' => $this->checkRegularPresences($member, 25),
            'regular_50' => $this->checkRegularPresences($member, 50),
            'regular_100' => $this->checkRegularPresences($member, 100),
            default => false,
        };
    }

    /**
     * Vérifie une série de présences consécutives
     */
    private function checkStreak(Member $member, int $days): bool
    {
        $presences = Presence::where('member_id', $member->id)
            ->orderBy('date', 'desc')
            ->take($days)
            ->pluck('date')
            ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
            ->toArray();

        if (count($presences) < $days) {
            return false;
        }

        // Vérifier que les dates sont consécutives
        $expectedDate = Carbon::now();
        foreach ($presences as $date) {
            if (Carbon::parse($date)->format('Y-m-d') !== $expectedDate->format('Y-m-d')) {
                return false;
            }
            $expectedDate->subDay();
        }

        return true;
    }

    /**
     * Vérifie si le membre a un mois parfait (100% de présence)
     */
    private function checkPerfectMonth(Member $member): bool
    {
        $lastMonth = Carbon::now()->subMonth();
        $startOfMonth = $lastMonth->copy()->startOfMonth();
        $endOfMonth = $lastMonth->copy()->endOfMonth();

        // Compter les sessions du mois dernier pour les groupes du membre
        $groupIds = $member->groups->pluck('id');

        $totalEvents = AttendanceSession::whereIn('group_id', $groupIds)
            ->whereBetween('event_date', [$startOfMonth, $endOfMonth])
            ->distinct('event_date')
            ->count('event_date');

        if ($totalEvents === 0) {
            return false;
        }

        // Compter les présences du membre
        $presences = Presence::where('member_id', $member->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->count();

        return $presences >= $totalEvents;
    }

    /**
     * Vérifie si le membre est souvent en avance (early bird)
     */
    private function checkEarlyBird(Member $member, int $threshold): bool
    {
        // Compter les présences avant 9h
        $earlyPresences = Presence::where('member_id', $member->id)
            ->whereNotNull('time')
            ->whereRaw("CAST(SUBSTR(time, 1, 2) AS INTEGER) < 9")
            ->count();

        return $earlyPresences >= $threshold;
    }

    /**
     * Vérifie la première présence
     */
    private function checkFirstPresence(Member $member): bool
    {
        return Presence::where('member_id', $member->id)->exists();
    }

    /**
     * Vérifie un nombre régulier de présences
     */
    private function checkRegularPresences(Member $member, int $count): bool
    {
        return Presence::where('member_id', $member->id)->count() >= $count;
    }

    /**
     * Attribue un badge à un membre
     */
    private function awardBadge(Member $member, Badge $badge): void
    {
        $member->badges()->attach($badge->id, [
            'earned_at' => now(),
            'metadata' => json_encode([
                'awarded_automatically' => true,
                'condition_met' => $badge->condition
            ])
        ]);
    }

    /**
     * Crée les badges par défaut
     */
    public static function createDefaultBadges(): void
    {
        $badges = [
            [
                'name' => 'Première Présence',
                'icon' => 'sparkle',
                'description' => 'Votre première présence enregistrée',
                'condition' => 'first_presence',
                'threshold' => 1,
                'points' => 10,
                'color' => 'green'
            ],
            [
                'name' => 'Série 7 jours',
                'icon' => 'flame',
                'description' => '7 jours de présence consécutifs',
                'condition' => 'streak_7',
                'threshold' => 7,
                'points' => 50,
                'color' => 'orange'
            ],
            [
                'name' => 'Série 14 jours',
                'icon' => 'bolt',
                'description' => '14 jours de présence consécutifs',
                'condition' => 'streak_14',
                'threshold' => 14,
                'points' => 100,
                'color' => 'purple'
            ],
            [
                'name' => 'Série 30 jours',
                'icon' => 'trophy',
                'description' => '30 jours de présence consécutifs',
                'condition' => 'streak_30',
                'threshold' => 30,
                'points' => 200,
                'color' => 'gold'
            ],
            [
                'name' => 'Mois Parfait',
                'icon' => 'star',
                'description' => '100% de présence sur un mois complet',
                'condition' => 'perfect_month',
                'threshold' => 1,
                'points' => 150,
                'color' => 'gold'
            ],
            [
                'name' => 'Lève-tôt',
                'icon' => 'sunrise',
                'description' => '10 présences avant 9h',
                'condition' => 'early_bird',
                'threshold' => 10,
                'points' => 30,
                'color' => 'blue'
            ],
            [
                'name' => 'Régulier (10)',
                'icon' => 'trend',
                'description' => '10 présences enregistrées',
                'condition' => 'regular_10',
                'threshold' => 10,
                'points' => 20,
                'color' => 'green'
            ],
            [
                'name' => 'Régulier (25)',
                'icon' => 'chart',
                'description' => '25 présences enregistrées',
                'condition' => 'regular_25',
                'threshold' => 25,
                'points' => 40,
                'color' => 'blue'
            ],
            [
                'name' => 'Régulier (50)',
                'icon' => 'target',
                'description' => '50 présences enregistrées',
                'condition' => 'regular_50',
                'threshold' => 50,
                'points' => 75,
                'color' => 'purple'
            ],
            [
                'name' => 'Centenaire',
                'icon' => 'award',
                'description' => '100 présences enregistrées',
                'condition' => 'regular_100',
                'threshold' => 100,
                'points' => 150,
                'color' => 'gold'
            ],
        ];

        foreach ($badges as $badge) {
            Badge::firstOrCreate(
                ['condition' => $badge['condition']],
                $badge
            );
        }
    }

    /**
     * Retourne les badges disponibles avec progression pour un membre
     */
    public function getBadgesWithProgress(Member $member): array
    {
        $badges = Badge::active()->get();
        $result = [];

        foreach ($badges as $badge) {
            $earned = $member->badges->contains($badge->id);
            $progress = $this->getProgress($member, $badge);

            $result[] = [
                'badge' => $badge,
                'earned' => $earned,
                'earned_at' => $earned 
                    ? $member->badges->find($badge->id)->pivot->earned_at 
                    : null,
                'progress' => $progress,
                'progress_percent' => min(100, round(($progress / $badge->threshold) * 100))
            ];
        }

        return $result;
    }

    /**
     * Calcule la progression vers un badge
     */
    private function getProgress(Member $member, Badge $badge): int
    {
        return match ($badge->condition) {
            'first_presence', 'regular_10', 'regular_25', 'regular_50', 'regular_100' 
                => Presence::where('member_id', $member->id)->count(),
            'early_bird' 
                => Presence::where('member_id', $member->id)
                    ->whereNotNull('time')
                    ->whereRaw("CAST(SUBSTR(time, 1, 2) AS INTEGER) < 9")
                    ->count(),
            default => 0,
        };
    }
}
