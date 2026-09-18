<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AnneeAcademique;
use App\Models\Etablissement;
use App\Models\Evenement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Passage d'une année académique à la suivante.
 *
 * La génération planifiée crée les séances quatorze jours à l'avance, depuis
 * l'emploi du temps de l'année active. Quand un établissement change d'année,
 * celles déjà créées pour l'année qu'il quitte n'ont plus lieu d'être : laissées
 * en place, elles seraient clôturées à leur heure sans que personne n'y
 * assiste, et chaque étudiant y serait compté absent. On retire donc les
 * séances à venir, jamais ouvertes et sans présence ; rien d'autre.
 */
class BasculeAnnee
{
    /**
     * Séances à retirer si les filières désignées quittent l'année $anneeId.
     *
     * @param  Collection<int, int|null>  $etablissements  établissements concernés ; null désigne les filières sans établissement
     */
    public function seancesAVenir(int $anneeId, Collection $etablissements): Builder
    {
        $ids = $etablissements->filter()->values();
        $sansEtablissement = $etablissements->contains(null);

        return Evenement::query()
            ->where('annee_id', $anneeId)
            ->where('statut', 'planifie')
            ->whereDate('date', '>', today())
            ->whereDoesntHave('presences')
            ->whereIn('filiere_id', function ($q) use ($ids, $sansEtablissement) {
                $q->select('id')->from('filieres')->where(function ($f) use ($ids, $sansEtablissement) {
                    $f->whereIn('etablissement_id', $ids->all());

                    if ($sansEtablissement) {
                        $f->orWhereNull('etablissement_id');
                    }
                });
            });
    }

    /**
     * Un établissement passe sur $nouvelle. Renvoie le nombre de séances de
     * l'année quittée qui ont été retirées.
     */
    public function basculer(int $etablissementId, AnneeAcademique $nouvelle): int
    {
        return DB::transaction(function () use ($etablissementId, $nouvelle) {
            $quittee = AnneeAcademique::activePour($etablissementId);

            Etablissement::whereKey($etablissementId)->update(['annee_active_id' => $nouvelle->id]);

            if (!$quittee || $quittee->id === $nouvelle->id) {
                return 0;
            }

            return $this->seancesAVenir($quittee->id, collect([$etablissementId]))->delete();
        });
    }

    /**
     * Établissements qui suivent l'année en cours de l'université : ceux qui
     * n'ont pas choisi la leur. null désigne les filières sans établissement.
     *
     * @return Collection<int, int|null>
     */
    public function suiveurs(): Collection
    {
        return Etablissement::whereNull('annee_active_id')->pluck('id')->push(null);
    }

    /**
     * L'université passe sur $nouvelle. Ceux qui ont choisi leur année la
     * gardent ; les autres suivent, et perdent les séances à venir de l'année
     * quittée. Renvoie le nombre de séances retirées.
     */
    public function changerAnneeEnCours(AnneeAcademique $nouvelle): int
    {
        return DB::transaction(function () use ($nouvelle) {
            $quittee = AnneeAcademique::where('active', true)->first();

            AnneeAcademique::where('active', true)->where('id', '!=', $nouvelle->id)->update(['active' => false]);
            $nouvelle->update(['active' => true]);

            if (!$quittee || $quittee->id === $nouvelle->id) {
                return 0;
            }

            return $this->seancesAVenir($quittee->id, $this->suiveurs())->delete();
        });
    }
}
