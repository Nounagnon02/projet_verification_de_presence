<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FermetureResource;
use App\Models\AnneeAcademique;
use App\Models\Fermeture;
use App\Models\PeriodeSemestre;
use App\Services\Planning\Calendrier;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Calendrier de l'établissement : périodes des semestres, vacances, examens.
 * Les jours fériés de l'université s'y lisent, sans s'y modifier.
 */
class CalendrierController extends Controller
{
    use ScopedByEtablissement;

    public function __construct(private readonly Calendrier $calendrier) {}

    /** GET /api/admin/calendrier?annee_id= (par défaut, l'année active de l'établissement) */
    public function index(Request $request): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);
        $annee = $request->filled('annee_id')
            ? AnneeAcademique::findOrFail($request->integer('annee_id'))
            : AnneeAcademique::activePour($etablissementId);

        if (!$annee) {
            return $this->successResponse(['annee' => null, 'periodes' => [], 'fermetures' => [], 'alertes' => []]);
        }

        $fermetures = FermetureResource::collection(Fermeture::where('annee_id', $annee->id)
            ->where(fn ($q) => $q->whereNull('etablissement_id')
                ->when($etablissementId, fn ($q) => $q->orWhere('etablissement_id', $etablissementId)))
            ->orderBy('date_debut')
            ->get());

        return $this->successResponse([
            'annee'      => [
                'id'         => $annee->id,
                'libelle'    => $annee->libelle,
                'date_debut' => $annee->date_debut->toDateString(),
                'date_fin'   => $annee->date_fin->toDateString(),
                'close'      => $annee->estClosePour($etablissementId),
            ],
            'periodes'   => $this->calendrier->periodesDe($etablissementId, $annee->id)->values(),
            'fermetures' => $fermetures,
            'alertes'    => $this->calendrier->alertes($etablissementId, $annee),
        ]);
    }

    /**
     * Déclare ou modifie la période d'une parité : dans l'année, sans
     * chevaucher celle de l'autre parité.
     *
     * PUT /api/admin/calendrier/periodes
     */
    public function enregistrerPeriode(Request $request): JsonResponse
    {
        $valeurs = $request->validate([
            'annee_id'   => 'required|integer|exists:annees_academiques,id',
            'parite'     => ['required', Rule::in(PeriodeSemestre::PARITES)],
            'date_debut' => 'required|date_format:Y-m-d',
            'date_fin'   => 'required|date_format:Y-m-d|after:date_debut',
        ], ['date_fin.after' => 'La fin des cours vient après leur début.']);

        $etablissementId = $this->getEtablissementId($request);
        $annee = AnneeAcademique::findOrFail($valeurs['annee_id']);
        $this->refuserSiAnneeClose($annee, $request);
        $this->calendrier->verifierDansAnnee($annee, $valeurs);

        $autre = $this->calendrier->periodesDe($etablissementId, $annee->id)->first(fn ($p) => $p->parite !== $valeurs['parite']);

        if ($autre && $autre->date_debut->toDateString() <= $valeurs['date_fin'] && $autre->date_fin->toDateString() >= $valeurs['date_debut']) {
            throw ValidationException::withMessages([
                'date_debut' => 'Cette période chevauche celle des semestres ' . ($autre->parite === 'pair' ? 'pairs' : 'impairs')
                    . ", du {$autre->date_debut->format('d/m/Y')} au {$autre->date_fin->format('d/m/Y')}.",
            ]);
        }

        $periode = PeriodeSemestre::updateOrCreate(
            ['etablissement_id' => $etablissementId, 'annee_id' => $annee->id, 'parite' => $valeurs['parite']],
            ['date_debut' => $valeurs['date_debut'], 'date_fin' => $valeurs['date_fin']]
        );

        return $this->successResponse(
            $periode,
            PeriodeSemestre::LIBELLES[$periode->parite] . " : cours du {$periode->date_debut->format('d/m/Y')} au {$periode->date_fin->format('d/m/Y')}."
        );
    }

    /** DELETE /api/admin/calendrier/periodes/{periode} */
    public function supprimerPeriode(Request $request, PeriodeSemestre $periode): JsonResponse
    {
        if ((int) $periode->etablissement_id !== (int) $this->getEtablissementId($request)) {
            abort(404, 'Ressource non trouvée.');
        }

        $this->refuserSiAnneeClose((int) $periode->annee_id, $request);
        $periode->delete();

        return $this->successResponse(null, PeriodeSemestre::LIBELLES[$periode->parite] . " : période retirée. Leurs séances ne sont plus générées depuis l'emploi du temps.");
    }

    /**
     * Vacances, examens ou autre fermeture de l'établissement. Avec apercu,
     * annonce seulement les séances à venir qu'elle retirerait.
     *
     * POST /api/admin/calendrier/fermetures
     */
    public function ajouterFermeture(Request $request): JsonResponse
    {
        $valeurs = $this->calendrier->validerFermeture($request->only(['annee_id', 'type', 'libelle', 'date_debut', 'date_fin']), Fermeture::TYPES_FACULTE);
        $this->refuserSiAnneeClose((int) $valeurs['annee_id'], $request);

        $resultat = $this->calendrier->apercuOuDeclaration(
            $valeurs + ['etablissement_id' => $this->getEtablissementId($request)],
            $request->boolean('apercu')
        );

        return $resultat['cree']
            ? $this->createdResponse($resultat['donnees'], $resultat['message'])
            : $this->successResponse($resultat['donnees'], $resultat['message']);
    }

    /** DELETE /api/admin/calendrier/fermetures/{fermeture} */
    public function supprimerFermeture(Request $request, Fermeture $fermeture): JsonResponse
    {
        if ($fermeture->etablissement_id === null) {
            return $this->errorResponse("Les jours fériés sont déclarés par le super administrateur de l'université : ils ne se retirent pas d'ici.", 403);
        }

        $this->authorizeEtablissement($fermeture, $request);
        $this->refuserSiAnneeClose((int) $fermeture->annee_id, $request);
        $fermeture->delete();

        return $this->successResponse(null, "« {$fermeture->libelle} » retirée. La génération planifiée recrée les séances de ces jours depuis l'emploi du temps.");
    }
}
