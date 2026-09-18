<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Etudiant;
use App\Models\Ue;

/**
 * Inscriptions des étudiants aux EC d'une UE, pour toutes les filières qui la
 * suivent (cours communs compris). Rattacher ou retirer une filière passe par
 * RegistreMaquette, qui inscrit ou désinscrit ses étudiants.
 */
class UeObserver
{
    /** Quand une UE change d'année, ses inscriptions changent d'année avec elle. */
    public function updated(Ue $ue): void
    {
        if (!$ue->wasChanged('annee_id')) {
            return;
        }

        $filieres = $ue->filieres()->pluck('filieres.id');
        $ecIds = $ue->ecs()->pluck('id')->all();

        foreach (Etudiant::whereIn('filiere_id', $filieres)->where('annee_id', $ue->getOriginal('annee_id'))->get() as $etudiant) {
            $etudiant->ecs()->detach($ecIds);
        }

        foreach (Etudiant::whereIn('filiere_id', $filieres)->where('annee_id', $ue->annee_id)->get() as $etudiant) {
            $etudiant->autoEnroll();
        }
    }
}
