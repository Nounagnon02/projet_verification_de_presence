<?php

namespace App\Observers;

use App\Models\Ec;
use App\Models\Etudiant;

class EcObserver
{
    /**
     * Quand un EC est créé, inscrit tous les étudiants de la filière+année
     * de son UE parente.
     */
    public function created(Ec $ec): void
    {
        $ec->load('ue');

        if (!$ec->ue) {
            return;
        }

        $students = Etudiant::where('filiere_id', $ec->ue->filiere_id)
            ->where('annee_id', $ec->ue->annee_id)
            ->get();

        foreach ($students as $student) {
            $student->ecs()->syncWithoutDetaching([
                $ec->id => ['annee_id' => $ec->ue->annee_id],
            ]);
        }
    }

    /**
     * Quand un EC change d'UE parente, recalcule les inscriptions.
     */
    public function updated(Ec $ec): void
    {
        if ($ec->wasChanged('ue_id')) {
            $ec->load('ue');

            $oldUeId = $ec->getOriginal('ue_id');
            $oldUe   = \App\Models\Ue::find($oldUeId);

            // Désinscrire les étudiants de l'ancienne UE
            if ($oldUe) {
                $oldStudents = Etudiant::where('filiere_id', $oldUe->filiere_id)
                    ->where('annee_id', $oldUe->annee_id)
                    ->get();

                foreach ($oldStudents as $student) {
                    $student->ecs()->detach($ec->id);
                }
            }

            // Inscrire les étudiants de la nouvelle UE
            if ($ec->ue) {
                $newStudents = Etudiant::where('filiere_id', $ec->ue->filiere_id)
                    ->where('annee_id', $ec->ue->annee_id)
                    ->get();

                foreach ($newStudents as $student) {
                    $student->ecs()->syncWithoutDetaching([
                        $ec->id => ['annee_id' => $ec->ue->annee_id],
                    ]);
                }
            }
        }
    }
}
