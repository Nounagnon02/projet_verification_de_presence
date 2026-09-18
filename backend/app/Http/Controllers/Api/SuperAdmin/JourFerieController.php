<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Fermeture;
use App\Services\Planning\Calendrier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Jours fériés de l'université, déclarés par le super administrateur : aucune
 * séance n'est générée ces jours-là, dans aucun établissement.
 */
class JourFerieController extends Controller
{
    public function __construct(private readonly Calendrier $calendrier) {}

    /** GET /api/super-admin/jours-feries?annee_id= */
    public function index(Request $request): JsonResponse
    {
        $feries = Fermeture::whereNull('etablissement_id')
            ->where('type', 'ferie')
            ->when($request->filled('annee_id'), fn ($q) => $q->where('annee_id', $request->integer('annee_id')))
            ->orderBy('date_debut')
            ->get();

        return $this->successResponse($feries);
    }

    /**
     * Avec apercu, annonce seulement les séances à venir qu'il retirerait, dans
     * tous les établissements.
     *
     * POST /api/super-admin/jours-feries
     */
    public function store(Request $request): JsonResponse
    {
        $valeurs = $this->calendrier->validerFermeture(
            ['type' => 'ferie'] + $request->only(['annee_id', 'libelle', 'date_debut', 'date_fin']),
            ['ferie']
        );

        $resultat = $this->calendrier->apercuOuDeclaration($valeurs + ['etablissement_id' => null], $request->boolean('apercu'));

        return $resultat['cree']
            ? $this->createdResponse($resultat['donnees'], $resultat['message'])
            : $this->successResponse($resultat['donnees'], $resultat['message']);
    }

    /** DELETE /api/super-admin/jours-feries/{fermeture} */
    public function destroy(Fermeture $fermeture): JsonResponse
    {
        if ($fermeture->etablissement_id !== null || $fermeture->type !== 'ferie') {
            abort(404, 'Ressource non trouvée.');
        }

        $fermeture->delete();

        return $this->successResponse(null, "« {$fermeture->libelle} » retiré. La génération planifiée recrée les séances de ce jour depuis l'emploi du temps.");
    }
}
