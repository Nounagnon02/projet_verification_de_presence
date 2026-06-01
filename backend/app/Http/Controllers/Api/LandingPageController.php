<?php

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
        $totalEtudiants = Etudiant::count();

        $evenementsPasses = Evenement::whereIn('statut', ['termine', 'en_cours'])->count();
        $evenementsTotal  = Evenement::count();

        $presencesValides = Presence::where('statut', 'valide')->count();
        $presencesTotal   = Presence::count();

        // Taux de présence global
        $totalPresencesPrevues = 0;
        $anneeActive = AnneeAcademique::where('active', true)->first();

        if ($anneeActive) {
            $totalPresencesPrevues = Etudiant::where('annee_id', $anneeActive->id)->count()
                * Evenement::where('annee_id', $anneeActive->id)
                    ->whereIn('statut', ['termine', 'en_cours'])
                    ->count();
        }

        $tauxPresence = ($totalPresencesPrevues > 0)
            ? round(($presencesValides / $totalPresencesPrevues) * 100, 1)
            : 0;

        return $this->successResponse([
            'total_etudiants'       => $totalEtudiants,
            'total_cours'           => $evenementsTotal,
            'presences_valides'     => $presencesValides,
            'presences_total'       => $presencesTotal,
            'taux_presence_global'  => $tauxPresence,
        ]);
    }
}
