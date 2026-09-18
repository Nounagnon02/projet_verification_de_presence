<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ec;
use App\Models\Evenement;
use App\Models\Salle;
use App\Services\ScheduleSlotResolver;
use App\Services\RegleSeanceService;
use App\Traits\ScopedByEtablissement;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvenementController extends Controller
{
    use ScopedByEtablissement;

    /**
     * Créneaux de l'emploi du temps correspondant à un EC et une date, pour
     * préremplir le formulaire de création d'événement.
     *
     * L'emploi du temps contient déjà la salle et les horaires de chaque séance
     * hebdomadaire~: les ressaisir à la main est une source d'erreurs et de
     * conflits de salle.
     *
     * GET /api/admin/evenements/creneaux-emploi-du-temps?ec_id=&date=
     */
    public function creneauxEmploiDuTemps(Request $request, ScheduleSlotResolver $resolver): JsonResponse
    {
        $validated = $request->validate([
            'ec_id' => ['required', 'integer', 'exists:ecs,id'],
            'date'  => ['required', 'date'],
        ]);

        // Cloisonnement : un admin d'entité ne doit pas explorer l'emploi du
        // temps d'un cours d'une autre entité. L'EC porte son établissement via
        // son UE puis sa filière.
        $ec = Ec::with('ue.filiere')->findOrFail($validated['ec_id']);

        if (!$ec->ue) {
            return $this->errorResponse("L'EC sélectionné n'est rattaché à aucune UE.", 422);
        }

        $this->authorizeEtablissement($ec->ue, $request, 'filiere');

        $date = Carbon::parse($validated['date']);

        $creneaux = $resolver->pourEcEtDate($ec->id, $date)->map(fn ($creneau) => [
            'id'          => $creneau->id,
            'heure_debut' => substr((string) $creneau->heure_debut, 0, 5),
            'heure_fin'   => substr((string) $creneau->heure_fin, 0, 5),
            'salle_id'    => $creneau->salle_id,
            'salle'       => $creneau->salle?->nom ?? $creneau->salle_libelle,
            'type_cours'  => $creneau->type_cours,
        ])->values();

        return $this->successResponse(
            $creneaux,
            $creneaux->isEmpty()
                ? "Aucun créneau à l'emploi du temps pour ce cours à cette date."
                : 'Créneaux récupérés.'
        );
    }

    public function index(Request $request): JsonResponse
    {
        // withCount plutôt que charger « presences » en entier : l'index n'a
        // besoin que du nombre, pas des lignes, qui pouvaient représenter tout
        // un semestre de scans par événement affiché.
        $query = Evenement::with(['ec.ue', 'filiere', 'groupe:id,libelle', 'qrCode', 'salleRef'])
            ->withCount('presences');

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
        // Filtre par EC : le formulaire d'ajout s'en sert pour montrer les séances
        // déjà programmées ce jour-là et éviter de découvrir le chevauchement au
        // moment de valider.
        if ($request->filled('ec_id')) {
            $query->where('ec_id', $request->ec_id);
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
                'type_cours'     => $e->type_cours,
                'groupe_id'      => $e->groupe_id,
                'groupe'         => $e->groupe ? ['id' => $e->groupe->id, 'libelle' => $e->groupe->libelle] : null,
                'salle_id'       => $e->salle_id,
                // Ce que la salle vérifie réellement au scan : l'interface ne doit
                // pas afficher « GPS + Wi-Fi » pour une salle qui n'a ni l'un ni
                // l'autre.
                'salle_ref'      => $e->salleRef ? [
                    'id'           => $e->salleRef->id,
                    'nom'          => $e->salleRef->nom,
                    'code'         => $e->salleRef->code,
                    'verifie_gps'  => $e->salleRef->verifieGps(),
                    'verifie_wifi' => $e->salleRef->verifieWifi(),
                ] : null,
                'statut'         => $e->statut,
                'ec'             => $e->ec ? ['id' => $e->ec->id, 'code' => $e->ec->code, 'intitule' => $e->ec->intitule] : null,
                // Lu par le formulaire de modification, et pour griser une séance d'année close.
                'annee_id'       => $e->annee_id,
                'ue'             => $e->ec && $e->ec->ue ? ['id' => $e->ec->ue->id, 'code' => $e->ec->ue->code] : null,
                'filiere'        => $e->filiere ? ['id' => $e->filiere->id, 'code' => $e->filiere->code] : null,
                'presences_count' => $e->presences_count,
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

    public function store(Request $request, RegleSeanceService $volumes): JsonResponse
    {
        $validated = $request->validate([
            'ec_id'       => 'required|exists:ecs,id',
            'date'        => 'required|date|after_or_equal:today',
            'heure_debut' => 'required|date_format:H:i',
            'heure_fin'   => 'required|date_format:H:i|after:heure_debut',
            'salle'       => 'nullable|string|max:100',
            'salle_id'    => 'nullable|exists:salles,id',
            // Un événement naît planifié. « En cours » et « Terminé » suivent la
            // séance (qrcode:auto-generate, events:close-finished) ; « Annulé »
            // se décide sur un événement existant, en modification.
            // CM, TD, TP ou évaluation : chaque type consomme son propre volume.
            'type_cours'  => 'nullable|string|in:cm,td,tp,evaluation',
            // Un groupe de TD ou de TP ; sans groupe, toute la promotion.
            'groupe_id'   => 'nullable|integer',
            'statut'      => 'sometimes|string|in:planifie',
        ], [
            'date.after_or_equal' => "La date d'un événement ne peut pas être antérieure à aujourd'hui.",
            'statut.in'           => "Un événement est créé au statut « Planifié ». Il passe ensuite « En cours » puis « Terminé » automatiquement ; une annulation se fait en modification.",
        ]);

        // La filière et l'année ne sont pas choisies : elles sont déduites de
        // l'EC (via son UE), pour qu'il soit impossible de créer un événement
        // rattaché à une filière incohérente avec le cours.
        $ec = \App\Models\Ec::with('ue')->findOrFail($validated['ec_id']);

        // Aucune barrière sur le statut « terminé » : le volume restant, par type
        // et par groupe, est vérifié plus bas. La barrière refusait aussi une
        // évaluation, qui ne consomme rien, et un groupe de TD pas encore servi.

        if (!$ec->ue) {
            return $this->errorResponse("L'EC sélectionné n'est rattaché à aucune UE.", 422);
        }

        // « exists:ecs,id » ne dit rien de l'établissement. La filière étant
        // déduite de l'EC, l'EC d'une autre faculté créait la séance chez elle.
        $this->authorizeEtablissement($ec->ue, $request, 'filiere');
        $this->autoriserSalle($validated['salle_id'] ?? null, $request);

        $validated['filiere_id'] = $ec->ue->filiere_id;
        $validated['annee_id']   = $ec->ue->annee_id;
        $this->refuserSiAnneeClose((int) $validated['annee_id'], $request);

        // Le créneau doit tenir dans le volume horaire restant de l'EC. Le
        // test « statut === termine » ci-dessus ne couvre que le cas déjà
        // épuisé, et il dépend d'une commande planifiée : il laissait
        // programmer quatre heures sur un EC qui n'en avait plus que deux.
        $validated['type_cours'] ??= 'cm';
        $validated['groupe_id'] = app(\App\Services\Groupes\GestionGroupes::class)
            ->verifierPourSeance($validated['groupe_id'] ?? null, $validated['type_cours'], $ec)?->id;

        if ($refus = $volumes->refus($ec, $validated['date'], $validated['heure_debut'], $validated['heure_fin'], type: $validated['type_cours'], groupeId: $validated['groupe_id'])) {
            return $this->errorResponse($refus, 422);
        }

        // Salle, promotion et groupe : la règle de l'emploi du temps. Seule la
        // salle était vérifiée ; deux cours de la même promotion au même moment
        // passaient.
        $conflits = app(\App\Services\Planning\Conflits::class)->pourSeance($ec, $validated);

        if ($conflits !== []) {
            return $this->errorResponse(implode(' ', $conflits), 422);
        }

        // Nom recopié depuis la salle configurée : c'est lui que les écrans affichent.
        if (!empty($validated['salle_id'])) {
            $validated['salle'] = Salle::find($validated['salle_id'])?->nom;
        }

        $evenement = Evenement::create($validated);
        return $this->createdResponse($evenement, 'Événement créé avec succès.');
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

    public function update(Request $request, Evenement $evenement, RegleSeanceService $volumes): JsonResponse
    {
        // Vérifier que l'admin a accès à cet événement (scope établissement)
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId && $evenement->filiere?->etablissement_id !== $etablissementId) {
            return $this->errorResponse('Événement non trouvé.', 404);
        }
        $this->refuserSiAnneeClose((int) $evenement->annee_id, $request);

        // Une date inchangée reste valable même si elle est passée. Le formulaire
        // renvoie la date avec le reste des champs : exiger « aujourd'hui ou
        // plus tard » dans tous les cas interdisait de marquer un cours d'hier
        // comme terminé ou annulé, ou d'en corriger la salle. Seule une date
        // réellement déplacée doit tomber aujourd'hui ou après.
        $dateDeplacee = $request->filled('date')
            && substr((string) $request->input('date'), 0, 10) !== $evenement->date->format('Y-m-d');

        $validated = $request->validate([
            'ec_id'       => 'sometimes|exists:ecs,id',
            'date'        => $dateDeplacee ? 'sometimes|date|after_or_equal:today' : 'sometimes|date',
            'heure_debut' => 'sometimes|date_format:H:i',
            'heure_fin'   => 'sometimes|date_format:H:i|after:heure_debut',
            'salle'       => 'nullable|string|max:100',
            'salle_id'    => 'nullable|exists:salles,id',
            'type_cours'  => 'sometimes|string|in:cm,td,tp,evaluation',
            'groupe_id'   => 'sometimes|nullable|integer',
            'statut'      => 'sometimes|string|in:planifie,en_cours,termine,annule',
        ], [
            'date.after_or_equal' => "La date d'un événement ne peut pas être antérieure à aujourd'hui.",
        ]);

        // Si l'EC change, filière et année sont re-déduites de l'EC — jamais
        // fournies par le client, pour éviter toute incohérence.
        if (!empty($validated['ec_id']) && $validated['ec_id'] != $evenement->ec_id) {
            $ec = \App\Models\Ec::with('ue')->findOrFail($validated['ec_id']);
            if (!$ec->ue) {
                return $this->errorResponse("L'EC sélectionné n'est rattaché à aucune UE.", 422);
            }
            // Seule la séance actuelle est contrôlée en tête de méthode : changer
            // son EC pour celui d'une autre faculté l'y déplaçait.
            $this->authorizeEtablissement($ec->ue, $request, 'filiere');
            $validated['filiere_id'] = $ec->ue->filiere_id;
            $validated['annee_id']   = $ec->ue->annee_id;
            $this->refuserSiAnneeClose((int) $validated['annee_id'], $request);
        }

        // Contrôle de conflit de salle sur les valeurs résultantes (nouvelles
        // si fournies, sinon celles déjà enregistrées), en excluant l'événement
        // lui-même.
        if (!empty($validated['salle_id']) && (int) $validated['salle_id'] !== (int) $evenement->salle_id) {
            $this->autoriserSalle($validated['salle_id'], $request);
        }

        $salleId    = array_key_exists('salle_id', $validated) ? $validated['salle_id'] : $evenement->salle_id;
        $date       = $validated['date']        ?? $evenement->date->format('Y-m-d');
        $heureDebut = $validated['heure_debut'] ?? $evenement->heure_debut;
        $heureFin   = $validated['heure_fin']   ?? $evenement->heure_fin;

        // Les contrôles de planification ne portent que sur un créneau qui bouge.
        // Une séance dont ni la salle, ni la date, ni les heures, ni le cours ne
        // changent ne peut pas créer de conflit qu'elle n'avait déjà. Sans cette
        // condition, marquer « terminée » une séance ancienne était refusé à
        // cause d'un conflit ou d'une durée hérités, antérieurs à la règle. La
        // réactivation d'une séance annulée, elle, réoccupe un créneau : elle est
        // vérifiée.
        // Groupe visé : cohérent avec le type et le cours, revérifié s'ils changent.
        $typeFinal = $validated['type_cours'] ?? $evenement->type_cours ?? 'cm';
        $groupeFinal = array_key_exists('groupe_id', $validated) ? $validated['groupe_id'] : $evenement->groupe_id;
        if (array_key_exists('groupe_id', $validated) || isset($validated['type_cours']) || isset($validated['ec_id'])) {
            $ecDuGroupe = Ec::with('ue')->find($validated['ec_id'] ?? $evenement->ec_id);
            $groupeFinal = $ecDuGroupe ? app(\App\Services\Groupes\GestionGroupes::class)->verifierPourSeance($groupeFinal, $typeFinal, $ecDuGroupe)?->id : null;
            $validated['groupe_id'] = $groupeFinal;
        }

        $statutFinal = $validated['statut'] ?? $evenement->statut;
        $replanifiee = ($salleId ?: null) != ($evenement->salle_id ?: null)
            || $date !== $evenement->date->format('Y-m-d')
            || substr((string) $heureDebut, 0, 5) !== substr((string) $evenement->heure_debut, 0, 5)
            || substr((string) $heureFin, 0, 5) !== substr((string) $evenement->heure_fin, 0, 5)
            || ($validated['ec_id'] ?? $evenement->ec_id) != $evenement->ec_id
            || ($validated['type_cours'] ?? $evenement->type_cours) !== $evenement->type_cours
            || ($groupeFinal ?: null) != ($evenement->groupe_id ?: null)
            || ($evenement->statut === 'annule' && $statutFinal !== 'annule');

        if ($replanifiee && $statutFinal !== 'annule') {
            $ecPlanifie = Ec::with('ue')->find($validated['ec_id'] ?? $evenement->ec_id);
            $conflits = $ecPlanifie ? app(\App\Services\Planning\Conflits::class)->pourSeance($ecPlanifie, [
                'date'        => $date,
                'heure_debut' => substr((string) $heureDebut, 0, 5),
                'heure_fin'   => substr((string) $heureFin, 0, 5),
                'salle_id'    => $salleId,
                'groupe_id'   => $groupeFinal,
            ], $evenement->id) : [];

            if ($conflits !== []) {
                return $this->errorResponse(implode(' ', $conflits), 422);
            }
        }

        // Même contrôle de volume qu'à la création. L'événement modifié est
        // exclu du décompte : sans cela, rallonger un créneau serait toujours
        // refusé puisqu'il se compterait lui-même. Une annulation libère au
        // contraire son créneau et n'a donc rien à vérifier.
        if ($replanifiee && $statutFinal !== 'annule') {
            $ecEffectif = Ec::find($validated['ec_id'] ?? $evenement->ec_id);
            if ($ecEffectif
                && $refus = $volumes->refus($ecEffectif, $date, $heureDebut, $heureFin, $evenement->id, type: $typeFinal, groupeId: $groupeFinal)) {
                return $this->errorResponse($refus, 422);
            }
        }

        // Le nom affiché partout (tableau de bord, grille, rapports, page de scan)
        // est evenements.salle : il est recopié de la salle choisie. Retirer une
        // salle configurée efface ce nom ; une séance qui n'en a jamais eu garde
        // le sien, faute de quoi l'enregistrer effacerait sa salle.
        if (array_key_exists('salle_id', $validated)) {
            if ($validated['salle_id']) {
                $validated['salle'] = Salle::find($validated['salle_id'])?->nom;
            } elseif ($evenement->salle_id) {
                $validated['salle'] = null;
            }
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
        $this->refuserSiAnneeClose((int) $evenement->annee_id, $request);

        $evenement->delete();
        return $this->successResponse(null, 'Événement supprimé.');
    }

    /**
     * Une salle désignée par son identifiant doit appartenir à l'établissement
     * de l'administrateur. « exists:salles,id » acceptait n'importe quelle salle
     * de l'université : une séance pouvait réserver celle d'une autre faculté.
     * 404, comme pour toute ressource hors périmètre, pour ne pas en confirmer
     * l'existence.
     */
    private function autoriserSalle(int|string|null $salleId, Request $request): void
    {
        if (!$salleId) {
            return;
        }

        $this->authorizeEtablissement(Salle::findOrFail($salleId), $request);
    }
}
