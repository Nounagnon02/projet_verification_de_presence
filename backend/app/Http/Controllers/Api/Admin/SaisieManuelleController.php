<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\PresenceManuelleImpossible;
use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Presence;
use App\Services\PresenceManuelleService;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Saisie par l'administration d'un étudiant qui n'a pas pu scanner :
 * téléphone déchargé ou oublié, application en panne.
 *
 * L'onglet « Saisie manuelle » était un formulaire de scan exigeant un QR en
 * cours : ouvert depuis le menu, il n'aboutissait jamais, et rien d'autre ne
 * permettait d'enregistrer un tel étudiant.
 */
class SaisieManuelleController extends Controller
{
    use ScopedByEtablissement;

    /**
     * Étudiants attendus à une séance, avec leur présence éventuelle.
     *
     * GET /api/admin/presence/manuelle/{evenement}/etudiants
     */
    public function etudiants(Request $request, int $evenement): JsonResponse
    {
        $seance = $this->seanceAccessible($request, $evenement);

        if (!$seance) {
            return $this->errorResponse('Séance introuvable.', 404);
        }

        $presences = Presence::where('evenement_id', $seance->id)
            ->get(['id', 'etudiant_id', 'statut'])
            ->keyBy('etudiant_id');

        $etudiants = Etudiant::attendusA($seance)
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get(['id', 'nom', 'prenom', 'matricule']);

        return $this->successResponse([
            'seance'    => [
                'id'          => $seance->id,
                'date'        => $seance->date->format('Y-m-d'),
                'heure_debut' => substr((string) $seance->heure_debut, 0, 5),
                'heure_fin'   => substr((string) $seance->heure_fin, 0, 5),
                'statut'      => $seance->statut,
                'cours'       => $seance->ec?->intitule,
                'code'        => $seance->ec?->code,
                'filiere'     => $seance->filiere?->code,
                'salle'       => $seance->salleRef?->nom ?? $seance->salle,
            ],
            'etudiants' => $etudiants->map(fn (Etudiant $e) => [
                'id'        => $e->id,
                'nom'       => $e->nom,
                'prenom'    => $e->prenom,
                'matricule' => $e->matricule,
                'presence'  => ($p = $presences->get($e->id)) ? ['id' => $p->id, 'statut' => $p->statut] : null,
            ])->values(),
        ]);
    }

    /**
     * Enregistre la présence d'un étudiant qui n'a pas pu scanner.
     *
     * POST /api/admin/presence/manuelle
     */
    public function enregistrer(Request $request, PresenceManuelleService $service): JsonResponse
    {
        $validator = validator($request->all(), [
            'evenement_id' => 'required|integer',
            'etudiant_id'  => 'required|uuid',
            'motif'        => 'required|string|max:500',
        ], [
            'motif.required' => 'Le motif est obligatoire : il justifie la présence enregistrée à la place du scan.',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $seance = $this->seanceAccessible($request, (int) $request->evenement_id);
        $etudiant = Etudiant::with('filiere:id,etablissement_id')->find($request->etudiant_id);
        $etablissementId = $this->getEtablissementId($request);

        if (!$seance || !$etudiant
            || ($etablissementId && (int) $etudiant->filiere?->etablissement_id !== (int) $etablissementId)) {
            return $this->errorResponse('Séance ou étudiant introuvable.', 404);
        }

        try {
            $presence = $service->enregistrer(
                $etudiant,
                $seance,
                $request->user(),
                $request->motif,
                // Pendant la séance, l'heure de la saisie ; après, son heure de
                // fin : la présence reste datée dans la séance.
                now()->min($seance->finCours()),
                'presence.saisie_manuelle',
            );
        } catch (PresenceManuelleImpossible $refus) {
            return $this->errorResponse($refus->getMessage(), $refus->statutHttp);
        }

        return $this->createdResponse([
            'presence' => ['id' => $presence->id, 'statut' => $presence->statut, 'heure_scan' => $presence->heure_scan],
        ], 'Présence enregistrée.');
    }

    /** Séance visible par l'utilisateur : celles de son établissement pour un admin de faculté. */
    private function seanceAccessible(Request $request, int $id): ?Evenement
    {
        $seance = Evenement::with(['ec:id,code,intitule', 'filiere:id,code,etablissement_id', 'salleRef:id,nom'])->find($id);
        $etablissementId = $this->getEtablissementId($request);

        if (!$seance || ($etablissementId && (int) $seance->filiere?->etablissement_id !== (int) $etablissementId)) {
            return null;
        }

        return $seance;
    }
}
