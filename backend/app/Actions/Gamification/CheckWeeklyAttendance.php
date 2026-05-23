<?php

namespace App\Actions\Gamification;

use App\Models\AuditLog;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Presence;
use Carbon\Carbon;

/**
 * Vérifie et récompense les semaines de présence parfaite.
 *
 * Conforme CDC 12.1 — Gamification :
 * Un étudiant qui assiste à TOUS les cours programmés dans sa filière
 * durant la semaine reçoit des points bonus.
 */
class CheckWeeklyAttendance
{
    /**
     * Points bonus pour une semaine parfaite.
     */
    private const BONUS_POINTS = 20;

    /**
     * Vérifie la semaine parfaite et attribue les points si éligible.
     *
     * @param  Etudiant $etudiant
     * @return array{perfect: bool, points_awarded: int, progress: string}
     */
    public function execute(Etudiant $etudiant): array
    {
        $now = Carbon::now();
        $startOfWeek = $now->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek   = $now->copy()->endOfWeek(Carbon::SUNDAY);
        $semaineIso  = $now->isoWeek();

        // Total des événements programmés cette semaine (hors annulés)
        $totalEvenements = Evenement::where('filiere_id', $etudiant->filiere_id)
            ->where('annee_id', $etudiant->annee_id)
            ->where('statut', '!=', 'annule')
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->count();

        if ($totalEvenements === 0) {
            return [
                'perfect'        => false,
                'points_awarded' => 0,
                'progress'       => '0/0',
            ];
        }

        // Présences valides de l'étudiant cette semaine
        $presencesEtudiant = Presence::where('etudiant_id', $etudiant->id)
            ->where('statut', 'valide')
            ->whereHas('evenement', function ($q) use ($startOfWeek, $endOfWeek) {
                $q->whereBetween('date', [$startOfWeek, $endOfWeek]);
            })
            ->count();

        // Pas encore parfait
        if ($presencesEtudiant < $totalEvenements) {
            return [
                'perfect'        => false,
                'points_awarded' => 0,
                'progress'       => "{$presencesEtudiant}/{$totalEvenements}",
            ];
        }

        // Vérifier si déjà récompensé cette semaine (anti-doublon)
        if ($this->dejaRecompense($etudiant, $semaineIso)) {
            return [
                'perfect'        => true,
                'points_awarded' => 0,
                'progress'       => "{$presencesEtudiant}/{$totalEvenements}",
            ];
        }

        // Attribution des points bonus
        $etudiant->increment('points', self::BONUS_POINTS);

        // Trace dans AuditLog
        AuditLog::create([
            'action'     => 'weekly_bonus',
            'model_type' => Etudiant::class,
            'model_id'   => $etudiant->id,
            'new_values' => [
                'semaine'         => $semaineIso,
                'points_ajoutes'  => self::BONUS_POINTS,
                'total_presences' => $presencesEtudiant,
                'total_evenements'=> $totalEvenements,
            ],
        ]);

        return [
            'perfect'        => true,
            'points_awarded' => self::BONUS_POINTS,
            'progress'       => "{$presencesEtudiant}/{$totalEvenements}",
        ];
    }

    /**
     * Vérifie dans AuditLog si la récompense a déjà été attribuée cette semaine.
     */
    private function dejaRecompense(Etudiant $etudiant, int $semaineIso): bool
    {
        return AuditLog::where('model_type', Etudiant::class)
            ->where('model_id', $etudiant->id)
            ->where('action', 'weekly_bonus')
            ->get()
            ->contains(fn (AuditLog $log) => ($log->new_values['semaine'] ?? null) === $semaineIso);
    }
}
