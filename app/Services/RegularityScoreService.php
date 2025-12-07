<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Presence;
use App\Models\QrCode;
use Illuminate\Support\Facades\DB;

class RegularityScoreService
{
    /**
     * Calcule le score de régularité d'un membre
     * Score = (nombre de présences / nombre d'événements programmés) × 100
     */
    public function calculateScore(Member $member): array
    {
        // Récupérer le nombre total d'événements (QR codes générés) pour le groupe du membre
        $totalEvents = QrCode::where('group', $member->group)
            ->distinct('event_date')
            ->count('event_date');

        // Récupérer le nombre de présences du membre
        $totalPresences = Presence::where('member_id', $member->id)->count();

        // Calculer le score
        $score = $totalEvents > 0 
            ? round(($totalPresences / $totalEvents) * 100, 1) 
            : 0;

        // Déterminer le niveau
        $level = $this->getLevel($score);

        return [
            'score' => $score,
            'total_presences' => $totalPresences,
            'total_events' => $totalEvents,
            'level' => $level,
            'stars' => $this->getStars($score),
            'color' => $this->getColor($level),
        ];
    }

    /**
     * Calcule les scores pour tous les membres d'un groupe
     */
    public function calculateScoresForGroup(string $group): array
    {
        $members = Member::where('group', $group)->get();
        $scores = [];

        foreach ($members as $member) {
            $scores[$member->id] = $this->calculateScore($member);
        }

        return $scores;
    }

    /**
     * Retourne le classement des membres par score
     */
    public function getRanking(string $group, int $limit = 10): array
    {
        $members = Member::where('group', $group)->get();
        $ranking = [];

        foreach ($members as $member) {
            $scoreData = $this->calculateScore($member);
            $ranking[] = [
                'member' => $member,
                'score' => $scoreData['score'],
                'level' => $scoreData['level'],
                'presences' => $scoreData['total_presences'],
                'events' => $scoreData['total_events'],
            ];
        }

        // Trier par score décroissant
        usort($ranking, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($ranking, 0, $limit);
    }

    /**
     * Détermine le niveau basé sur le score
     */
    private function getLevel(float $score): string
    {
        if ($score >= 90) {
            return 'excellent';
        } elseif ($score >= 70) {
            return 'good';
        } elseif ($score >= 50) {
            return 'average';
        } elseif ($score >= 30) {
            return 'low';
        }
        return 'critical';
    }

    /**
     * Retourne le nombre d'étoiles (1-5)
     */
    private function getStars(float $score): int
    {
        if ($score >= 90) return 5;
        if ($score >= 75) return 4;
        if ($score >= 60) return 3;
        if ($score >= 40) return 2;
        return 1;
    }

    /**
     * Retourne la couleur associée au niveau
     */
    private function getColor(string $level): string
    {
        return match ($level) {
            'excellent' => 'green',
            'good' => 'blue',
            'average' => 'yellow',
            'low' => 'orange',
            'critical' => 'red',
            default => 'gray',
        };
    }
}
