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
            ->where(fn ($q) => $q->whereNull('valide_du')->orWhereDate('valide_du', '<=', $date->toDateString()))
            ->where(fn ($q) => $q->whereNull('valide_au')->orWhereDate('valide_au', '>=', $date->toDateString()))
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
        // Le jour de la semaine, et la version de l'emploi du temps qui vaut ce jour-là.
        return $creneaux->where('jour_semaine', $this->jourSemaine($date))
            ->filter(fn (EmploiDuTemps $c) => $c->valableLe($date));
    }

    /**
     * Séances à venir générées depuis ce créneau, jamais ouvertes et sans
     * présence : celles qu'une modification ou une suppression du créneau rend
     * caduques. Celles d'aujourd'hui peuvent être en cours : on n'y touche pas.
     */
    public function seancesAVenir(EmploiDuTemps $creneau): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Models\Evenement::query()
            ->where('ec_id', $creneau->ec_id)
            ->where('statut', 'planifie')
            ->whereDate('date', '>', today())
            ->whereRaw('extract(isodow from date) = ?', [(int) $creneau->jour_semaine])
            ->where('heure_debut', $creneau->heure_debut)
            ->when($creneau->groupe_id, fn ($q) => $q->where('groupe_id', $creneau->groupe_id), fn ($q) => $q->whereNull('groupe_id'))
            ->whereDoesntHave('presences');
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
            // Le nom de la salle configurée : un créneau importé par l'IA n'avait
            // pas de salle_libelle, et ses séances s'affichaient sans salle.
            'salle'       => $creneau->salle?->nom ?? $creneau->salle_libelle,
            'salle_id'    => $creneau->salle_id,
            'type_cours'  => \App\Support\TypeCours::normaliser($creneau->type_cours),
            'groupe_id'   => $creneau->groupe_id,
            'statut'      => 'planifie',
        ];
    }
}
