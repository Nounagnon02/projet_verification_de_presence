<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvenementController extends Controller
{
    use ScopedByEtablissement;

    public function index(Request $request): JsonResponse
    {
        $query = Evenement::with(['ec.ue', 'filiere', 'presences', 'qrCode', 'salleRef']);

        // Scope par établissement via la filière
        $this->scopeViaRelation($query, $request, 'filiere');

        if ($request->filled('date_debut')) {
            $query->where('date', '>=', $request->date_debut);
        }
        if ($request->filled('date_fin')) {
            $query->where('date', '<=', $request->date_fin);
        }
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }
        if ($request->filled('filiere_id')) {
            $query->where('filiere_id', $request->filiere_id);
        }
        if ($request->filled('annee_id')) {
            $query->where('annee_id', $request->annee_id);
        }
        if ($request->filled('semestre')) {
            $query->whereHas('ec.ue', fn($q) => $q->where('semestre', $request->semestre));
        }
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        $evenements = $query->orderBy('date', 'asc')
            ->orderBy('heure_debut', 'asc')
            ->get()
            ->map(fn($e) => [
                'id'             => $e->id,
                'date'           => $e->date->format('Y-m-d'),
                'heure_debut'    => $e->heure_debut,
                'heure_fin'      => $e->heure_fin,
                'salle'          => $e->salle,
                'salle_id'       => $e->salle_id,
                'salle_ref'      => $e->salleRef ? [
                    'id'     => $e->salleRef->id,
                    'nom'    => $e->salleRef->nom,
                    'code'   => $e->salleRef->code,
                ] : null,
                'statut'         => $e->statut,
                'ec'             => $e->ec ? ['id' => $e->ec->id, 'code' => $e->ec->code, 'intitule' => $e->ec->intitule] : null,
                'ue'             => $e->ec && $e->ec->ue ? ['id' => $e->ec->ue->id, 'code' => $e->ec->ue->code] : null,
                'filiere'        => $e->filiere ? ['id' => $e->filiere->id, 'code' => $e->filiere->code] : null,
                'presences_count' => $e->presences->count(),
                'has_qr_code'    => $e->qrCode ? true : false,
                'qr_code'        => $e->qrCode ? [
                    'id'         => $e->qrCode->id,
                    'token'      => $e->qrCode->token,
                    'expire_at'  => $e->qrCode->expire_at?->format('Y-m-d H:i:s'),
                    'actif'      => $e->qrCode->actif,
                    'is_expired' => $e->qrCode->isExpired(),
                ] : null,
            ]);

        return $this->successResponse($evenements);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ec_id'       => 'required|exists:ecs,id',
            'date'        => 'required|date|after_or_equal:today',
            'heure_debut' => 'required|date_format:H:i',
            'heure_fin'   => 'required|date_format:H:i|after:heure_debut',
            'salle'       => 'nullable|string|max:100',
            'salle_id'    => 'nullable|exists:salles,id',
            'statut'      => 'sometimes|string|in:planifie,en_cours,termine,annule',
        ]);

        // La filière et l'année ne sont pas choisies : elles sont déduites de
        // l'EC (via son UE), pour qu'il soit impossible de créer un événement
        // rattaché à une filière incohérente avec le cours.
        $ec = \App\Models\Ec::with('ue')->findOrFail($validated['ec_id']);

        if ($ec->statut === 'termine') {
            return $this->errorResponse(
                "L'EC {$ec->code} ({$ec->intitule}) a déjà atteint son volume horaire ({$ec->volume_horaire}h). Il n'est plus possible d'ajouter des événements.",
                422
            );
        }

        if (!$ec->ue) {
            return $this->errorResponse("L'EC sélectionné n'est rattaché à aucune UE.", 422);
        }

        $validated['filiere_id'] = $ec->ue->filiere_id;
        $validated['annee_id']   = $ec->ue->annee_id;

        if ($conflit = $this->salleConflit($validated['salle_id'] ?? null, $validated['date'], $validated['heure_debut'], $validated['heure_fin'])) {
            return $this->errorResponse($conflit, 422);
        }

        $evenement = Evenement::create($validated);
        return $this->createdResponse($evenement, 'Événement créé avec succès.');
    }

    /**
     * Renvoie un message d'erreur si la salle est déjà occupée sur un créneau
     * qui chevauche, sinon null.
     *
     * Deux créneaux se chevauchent si debut_A < fin_B ET fin_A > debut_B, le
     * même jour et dans la même salle. Les événements annulés ne réservent
     * pas la salle. Sur une modification, on exclut l'événement lui-même.
     */
    private function salleConflit(?int $salleId, string $date, string $heureDebut, string $heureFin, ?int $exclureId = null): ?string
    {
        if (!$salleId) {
            return null; // pas de salle structurée → pas de contrôle possible
        }

        $conflit = Evenement::where('salle_id', $salleId)
            ->where('date', $date)
            ->where('statut', '!=', 'annule')
            ->when($exclureId, fn ($q) => $q->where('id', '!=', $exclureId))
            ->where('heure_debut', '<', $heureFin)
            ->where('heure_fin', '>', $heureDebut)
            ->with('ec')
            ->first();

        if (!$conflit) {
            return null;
        }

        $cours = $conflit->ec?->intitule ?? 'un autre cours';

        return "La salle est déjà occupée le {$date} de {$conflit->heure_debut} à {$conflit->heure_fin} "
            . "({$cours}). Choisissez une autre salle ou un autre créneau.";
    }

    public function show(Request $request, Evenement $evenement): JsonResponse
    {
        // Vérifier que l'admin a accès à cet événement (scope établissement)
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId && $evenement->filiere?->etablissement_id !== $etablissementId) {
            return $this->errorResponse('Événement non trouvé.', 404);
        }

        $evenement->load(['ec.ue', 'filiere', 'presences.etudiant', 'qrCode', 'salleRef']);
        return $this->successResponse($evenement);
    }

    public function update(Request $request, Evenement $evenement): JsonResponse
    {
        // Vérifier que l'admin a accès à cet événement (scope établissement)
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId && $evenement->filiere?->etablissement_id !== $etablissementId) {
            return $this->errorResponse('Événement non trouvé.', 404);
        }

        $validated = $request->validate([
            'ec_id'       => 'sometimes|exists:ecs,id',
            'date'        => 'sometimes|date|after_or_equal:today',
            'heure_debut' => 'sometimes|date_format:H:i',
            'heure_fin'   => 'sometimes|date_format:H:i|after:heure_debut',
            'salle'       => 'nullable|string|max:100',
            'salle_id'    => 'nullable|exists:salles,id',
            'statut'      => 'sometimes|string|in:planifie,en_cours,termine,annule',
        ]);

        // Si l'EC change, filière et année sont re-déduites de l'EC — jamais
        // fournies par le client, pour éviter toute incohérence.
        if (!empty($validated['ec_id']) && $validated['ec_id'] != $evenement->ec_id) {
            $ec = \App\Models\Ec::with('ue')->findOrFail($validated['ec_id']);
            if ($ec->statut === 'termine') {
                return $this->errorResponse(
                    "L'EC {$ec->code} ({$ec->intitule}) a déjà atteint son volume horaire ({$ec->volume_horaire}h).",
                    422
                );
            }
            if (!$ec->ue) {
                return $this->errorResponse("L'EC sélectionné n'est rattaché à aucune UE.", 422);
            }
            $validated['filiere_id'] = $ec->ue->filiere_id;
            $validated['annee_id']   = $ec->ue->annee_id;
        }

        // Contrôle de conflit de salle sur les valeurs résultantes (nouvelles
        // si fournies, sinon celles déjà enregistrées), en excluant l'événement
        // lui-même.
        $salleId    = array_key_exists('salle_id', $validated) ? $validated['salle_id'] : $evenement->salle_id;
        $date       = $validated['date']        ?? $evenement->date->format('Y-m-d');
        $heureDebut = $validated['heure_debut'] ?? $evenement->heure_debut;
        $heureFin   = $validated['heure_fin']   ?? $evenement->heure_fin;

        if (($validated['statut'] ?? $evenement->statut) !== 'annule'
            && $conflit = $this->salleConflit($salleId, $date, $heureDebut, $heureFin, $evenement->id)) {
            return $this->errorResponse($conflit, 422);
        }

        $evenement->update($validated);
        return $this->successResponse($evenement, 'Événement mis à jour.');
    }

    public function destroy(Request $request, Evenement $evenement): JsonResponse
    {
        // Vérifier que l'admin a accès à cet événement (scope établissement)
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId && $evenement->filiere?->etablissement_id !== $etablissementId) {
            return $this->errorResponse('Événement non trouvé.', 404);
        }

        $evenement->delete();
        return $this->successResponse(null, 'Événement supprimé.');
    }
}
