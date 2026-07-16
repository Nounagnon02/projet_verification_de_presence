<?php

namespace App\Observers;

use App\Models\Ec;
use App\Models\Etudiant;
use App\Models\Ue;

class UeObserver
{
    /**
     * Quand une UE est créée, inscrit tous les étudiants de la filière+année
     * aux ECs de cette nouvelle UE.
     */
    public function created(Ue $ue): void
    {
        $students = Etudiant::where('filiere_id', $ue->filiere_id)
            ->where('annee_id', $ue->annee_id)
            ->get();

        foreach ($students as $student) {
            foreach ($ue->ecs as $ec) {
                $student->ecs()->syncWithoutDetaching([
                    $ec->id => ['annee_id' => $ue->annee_id],
                ]);
            }
        }
    }

    /**
     * Quand une UE est modifiée (filière ou année), recalcule les inscriptions
     * des étudiants concernés.
     */
    public function updated(Ue $ue): void
    {
        if ($ue->wasChanged('filiere_id') || $ue->wasChanged('annee_id')) {
            // Ancienne filière/année
            $oldFiliereId = $ue->getOriginal('filiere_id');
            $oldAnneeId   = $ue->getOriginal('annee_id');

            // Désinscrire les étudiants de l'ancienne combinaison
            if ($oldFiliereId && $oldAnneeId) {
                $oldStudents = Etudiant::where('filiere_id', $oldFiliereId)
                    ->where('annee_id', $oldAnneeId)
                    ->get();

                foreach ($oldStudents as $student) {
                    $student->ecs()->detach($ue->ecs->pluck('id')->toArray());
                }
            }

            // Inscrire les étudiants de la nouvelle combinaison
            $newStudents = Etudiant::where('filiere_id', $ue->filiere_id)
                ->where('annee_id', $ue->annee_id)
                ->get();

            foreach ($newStudents as $student) {
                foreach ($ue->ecs as $ec) {
                    $student->ecs()->syncWithoutDetaching([
                        $ec->id => ['annee_id' => $ue->annee_id],
                    ]);
                }
            }
        }
    }
}
