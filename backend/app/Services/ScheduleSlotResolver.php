<?php

namespace App\Services;

use App\Models\EmploiDuTemps;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Résout les créneaux de l'emploi du temps applicables à une date donnée.
 *
 * Ce service est le seul endroit où l'on traduit une date en jour de semaine
 * pour interroger l'emploi du temps. La commande de génération automatique des
 * événements et le préremplissage du formulaire de création s'appuient tous
 * deux sur lui : sans cela, les deux chemins pourraient dériver et proposer des
 * créneaux différents pour la même date.
 */
class ScheduleSlotResolver
{
    /**
     * Numéro de jour ISO attendu par la colonne `jour_semaine`
     * (1 = lundi … 7 = dimanche).
     */
    public function jourSemaine(Carbon $date): int
    {
        return $date->dayOfWeekIso;
    }

    /**
     * Créneaux d'un EC pour une date donnée.
     *
     * Renvoie une collection et non un créneau unique : un même EC peut avoir
     * plusieurs séances le même jour (un cours le matin, un TD l'après-midi).
     * C'est à l'appelant de choisir, ou de laisser l'utilisateur choisir.
     *
     * @return Collection<int, EmploiDuTemps>
     */
    public function pourEcEtDate(int $ecId, Carbon $date): Collection
    {
        return EmploiDuTemps::with('salle')
            ->where('ec_id', $ecId)
            ->where('jour_semaine', $this->jourSemaine($date))
            ->orderBy('heure_debut')
            ->get();
    }

    /**
     * Tous les créneaux tombant un jour donné, quel que soit l'EC.
     *
     * @param  Collection<int, EmploiDuTemps>  $creneaux  jeu déjà chargé, pour
     *         éviter une requête par jour lors d'une génération sur plusieurs
     *         semaines.
     * @return Collection<int, EmploiDuTemps>
     */
    public function filtrerPourDate(Collection $creneaux, Carbon $date): Collection
    {
        return $creneaux->where('jour_semaine', $this->jourSemaine($date));
    }

    /**
     * Traduit un créneau en attributs d'événement. Utilisé à la fois pour créer
     * les événements automatiquement et pour préremplir le formulaire, afin que
     * les deux produisent exactement les mêmes valeurs.
     *
     * @return array<string, mixed>
     */
    public function versAttributsEvenement(EmploiDuTemps $creneau, Carbon $date): array
    {
        return [
            'ec_id'       => $creneau->ec_id,
            'filiere_id'  => $creneau->filiere_id,
            'annee_id'    => $creneau->annee_id,
            'date'        => $date->format('Y-m-d'),
            'heure_debut' => $creneau->heure_debut,
            'heure_fin'   => $creneau->heure_fin,
            'salle'       => $creneau->salle_libelle,
            'salle_id'    => $creneau->salle_id,
            'statut'      => 'planifie',
        ];
    }
}
