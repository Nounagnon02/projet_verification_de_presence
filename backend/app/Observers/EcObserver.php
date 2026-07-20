<?php

namespace App\Observers;

use App\Models\Ec;

/**
 * Met à jour automatiquement le volume_horaire de l'UE
 * quand un EC est créé, modifié ou supprimé.
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

    public function created(Ec $ec): void
    {
        $this->syncUeVolume($ec);
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
    }

    public function deleted(Ec $ec): void
    {
        $this->syncUeVolume($ec);
    }
}
