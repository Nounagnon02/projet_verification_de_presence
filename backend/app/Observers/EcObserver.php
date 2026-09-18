<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Ec;
use App\Models\Etudiant;
use Illuminate\Support\Facades\DB;

/**
 * Met à jour le volume_horaire de l'UE quand un EC est créé, modifié ou
 * supprimé ; inscrit à l'EC les étudiants que son UE attend.
 */
class EcObserver
{
    /**
     * Recalcule le volume_horaire de l'UE en faisant la somme
     * des volume_horaire de tous ses ECs.
     */
    private function syncUeVolume(Ec $ec): void
    {
        $total = Ec::where('ue_id', $ec->ue_id)->sum('volume_horaire');
        $ec->ue()->update(['volume_horaire' => $total]);
    }

    /**
     * Inscrit à l'EC les étudiants de chaque filière qui suit son UE, pour son
     * année. Une maquette importée après les inscriptions ne laissait personne
     * inscrit à ses cours : leurs séances n'attendaient aucun étudiant, et le
     * scan les refusait. Quand l'EC change d'UE, ceux que la nouvelle UE
     * n'attend pas sont désinscrits.
     */
    private function inscrireLesEtudiants(Ec $ec, bool $recalculer = false): void
    {
        $ue = \App\Models\Ue::query()->find($ec->ue_id, ['id', 'annee_id']);

        if (!$ue) {
            return;
        }

        $etudiants = Etudiant::query()
            ->whereIn('filiere_id', DB::table('ue_filiere')->where('ue_id', $ue->id)->select('filiere_id'))
            ->where('annee_id', $ue->annee_id)
            ->pluck('id');

        if ($recalculer) {
            DB::table('etudiant_ec')->where('ec_id', $ec->id)->whereNotIn('etudiant_id', $etudiants)->delete();
        }

        if ($etudiants->isNotEmpty()) {
            $maintenant = now();
            DB::table('etudiant_ec')->insertOrIgnore($etudiants->map(fn ($id) => [
                'etudiant_id' => $id, 'ec_id' => $ec->id, 'annee_id' => $ue->annee_id,
                'created_at' => $maintenant, 'updated_at' => $maintenant,
            ])->all());
        }
    }

    public function created(Ec $ec): void
    {
        $this->syncUeVolume($ec);
        $this->inscrireLesEtudiants($ec);
    }

    public function updated(Ec $ec): void
    {
        // Si l'EC a changé d'UE, recalculer aussi l'ancienne UE
        if ($ec->isDirty('ue_id')) {
            $oldUeId = $ec->getOriginal('ue_id');
            if ($oldUeId) {
                $oldTotal = Ec::where('ue_id', $oldUeId)->sum('volume_horaire');
                \App\Models\Ue::where('id', $oldUeId)->update(['volume_horaire' => $oldTotal]);
            }
        }

        if ($ec->isDirty('volume_horaire') || $ec->isDirty('ue_id')) {
            $this->syncUeVolume($ec);
        }

        if ($ec->isDirty('ue_id')) {
            $this->inscrireLesEtudiants($ec, recalculer: true);
        }
    }

    public function deleted(Ec $ec): void
    {
        $this->syncUeVolume($ec);
    }
}
