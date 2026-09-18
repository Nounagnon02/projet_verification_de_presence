<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Presence;
use Illuminate\Http\JsonResponse;

/**
 * Controller public pour la Landing Page.
 * Aucune authentification requise.
 */
class LandingPageController extends Controller
{
    /**
     * Statistiques globales pour la landing page.
     *
     * GET /api/landing/stats
     */
    public function stats(): JsonResponse
    {
        $totalEtudiants   = Etudiant::count();
        $evenementsTotal  = Evenement::count();

        $presencesValides = Presence::where('statut', 'valide')->count();
        $presencesTotal   = Presence::count();

        // Taux de présence de l'année active.
        //
        // Le dénominateur est le nombre de présences *attendues*, c'est-à-dire
        // le nombre de couples (séance passée, étudiant inscrit à l'EC de cette
        // séance). Compter « tous les étudiants × toutes les séances »
        // reviendrait à supposer chaque étudiant inscrit à chaque cours de
        // l'année, ce qui gonfle le dénominateur et écrase artificiellement le
        // taux. Numérateur et dénominateur portent tous deux sur l'année active.
        $anneeActive = AnneeAcademique::where('active', true)->first();
        $tauxPresence = 0;

        if ($anneeActive) {
            // Colonnes qualifiées : la requête est réutilisée avec une jointure
            // sur etudiant_ec, qui porte les mêmes noms de colonnes.
            $seancesPassees = Evenement::query()
                ->where('evenements.annee_id', $anneeActive->id)
                ->whereIn('evenements.statut', ['termine', 'en_cours']);

            $presencesPrevues = (clone $seancesPassees)
                ->join('etudiant_ec', function ($jointure) {
                    $jointure->on('etudiant_ec.ec_id', '=', 'evenements.ec_id')
                        ->on('etudiant_ec.annee_id', '=', 'evenements.annee_id');
                })
                ->count();

            $presencesValidesAnnee = Presence::where('statut', 'valide')
                ->whereIn('evenement_id', (clone $seancesPassees)->select('evenements.id'))
                ->count();

            $tauxPresence = $presencesPrevues > 0
                ? round(($presencesValidesAnnee / $presencesPrevues) * 100, 1)
                : 0;
        }

        return $this->successResponse([
            'total_etudiants'       => $totalEtudiants,
            'total_cours'           => $evenementsTotal,
            'presences_valides'     => $presencesValides,
            'presences_total'       => $presencesTotal,
            'taux_presence_global'  => $tauxPresence,
        ]);
    }
}
