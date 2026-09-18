<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\PresenceManuelleImpossible;
use App\Http\Controllers\Controller;
use App\Models\Anomaly;
use App\Models\Evenement;
use App\Models\Presence;
use App\Services\PresenceManuelleService;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AlertController extends Controller
{
    use ScopedByEtablissement;

    /**
     * Scans refusés : tentatives que le serveur a rejetées — hors zone GPS ou
     * Wi-Fi, défi de sécurité invalide, second scan depuis un autre téléphone.
     *
     * Lecture seule. Aucune présence n'a été créée, il n'y a donc rien à
     * arbitrer : la liste sert à comprendre un refus (réclamation d'un
     * étudiant, tentatives répétées). Les scans suspects se tranchent dans la
     * file de validation (GET /admin/presence/pending), seul lieu de décision.
     *
     * Cette liste proposait auparavant « Valide » et « Ignorer » sur toutes
     * les anomalies. Sur un refus, les deux ne faisaient que fermer l'alerte ;
     * et « Valide » sur un double scan repassait une présence en « valide »,
     * sans motif ni trace, par-dessus un rejet décidé dans la file.
     *
     * GET /api/admin/alerts
     */
    public function index(Request $request): JsonResponse
    {
        $query = Anomaly::with('etudiant.filiere')
            ->whereIn('type', Anomaly::TYPES_SCAN_REFUSE);

        // Cloisonnement : un admin de faculté ne voit que les refus de ses
        // propres étudiants (via filiere.etablissement_id).
        if ($etablissementId = $this->getEtablissementId($request)) {
            $query->whereHas('etudiant.filiere', fn ($q) => $q->where('etablissement_id', $etablissementId));
        }

        if ($request->filled('filiere_id')) {
            $query->whereHas('etudiant', fn ($q) => $q->where('filiere_id', $request->filiere_id));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $terme = '%' . addcslashes(trim((string) $request->search), '%_\\') . '%';
            $query->whereHas('etudiant', fn ($q) => $q->where(fn ($q) => $q
                ->where('nom', 'ilike', $terme)
                ->orWhere('prenom', 'ilike', $terme)
                ->orWhere('matricule', 'ilike', $terme)
                ->orWhereRaw("prenom || ' ' || nom ilike ?", [$terme])));
        }

        $refus = $query->latest()->latest('id')->paginate(min($request->integer('per_page', 20), 100));
        $seances = $this->seancesVisees($refus->getCollection());
        $presences = $this->presencesDesRefus($refus->getCollection(), $seances);

        // paginatedResponse(), pas successResponse() : ce dernier renvoyait le
        // paginateur Laravel tel quel dans « data » ({current_page, data: [...],
        // total, ...}), le seul endpoint de liste à s'écarter du contrat
        // {data: [...], meta: {...}} partagé par students, presence/history,
        // notifications, tickets et evenements.
        return $this->paginatedResponse($refus->through(fn (Anomaly $a) => [
            'id'          => $a->id,
            'type'        => $a->type,
            'description' => $a->description,
            'etudiant'    => $a->etudiant ? [
                'id'        => $a->etudiant->id,
                'nom'       => $a->etudiant->nom,
                'prenom'    => $a->etudiant->prenom,
                'matricule' => $a->etudiant->matricule,
                'filiere'   => $a->etudiant->filiere?->code,
            ] : null,
            'evenement'   => $seances[$a->id] ?? null,
            // Présence de l'étudiant à cette séance, s'il en a une : elle dit
            // quelle suite a été donnée au refus.
            'presence'    => ($p = $presences->get($a->etudiant_id . '|' . ($seances[$a->id]['id'] ?? ''))) ? [
                'id'              => $p->id,
                'statut'          => $p->statut,
                'depuis_ce_refus' => ($a->metadata['presence_id'] ?? null) === $p->id,
            ] : null,
            'creee_le'    => $a->created_at,
        ]), 'Scans refusés récupérés.');
    }

    /**
     * Enregistre la présence d'un étudiant dont le scan a été refusé à tort.
     *
     * Cas d'usage : l'étudiant était en salle, mais le GPS de son téléphone
     * était imprécis ou le Wi-Fi mal capté. Rien d'autre ne permettait de le
     * rattraper : seul le scan crée une présence, et il n'est accepté que
     * pendant la fenêtre de présence.
     *
     * L'étudiant et la séance viennent du refus, pas du formulaire : une
     * présence n'est rattachée qu'à une tentative réelle. Motif obligatoire,
     * heure de la tentative conservée, opération tracée au journal d'audit.
     *
     * POST /api/admin/alerts/{id}/presence
     */
    public function enregistrerPresence(Request $request, int $id, PresenceManuelleService $service): JsonResponse
    {
        $validator = validator($request->all(), [
            'motif' => 'required|string|max:500',
        ], [
            'motif.required' => 'Le motif est obligatoire : il justifie la présence enregistrée à la place du scan.',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $refus = Anomaly::with('etudiant.filiere')->whereIn('type', Anomaly::TYPES_SCAN_REFUSE)->find($id);
        $etablissementId = $this->getEtablissementId($request);

        if (!$refus || !$refus->etudiant
            || ($etablissementId && (int) $refus->etudiant->filiere?->etablissement_id !== (int) $etablissementId)) {
            return $this->errorResponse('Scan refusé introuvable.', 404);
        }

        $seance = $this->seancesVisees(collect([$refus]))[$refus->id] ?? null;
        $evenement = $seance ? Evenement::find($seance['id']) : null;

        if (!$evenement) {
            return $this->errorResponse("La séance visée n'a pas été notée pour ce refus : impossible d'y rattacher une présence.", 422);
        }

        try {
            $presence = $service->enregistrer(
                $refus->etudiant,
                $evenement,
                $request->user(),
                $request->motif,
                // L'heure de la tentative : c'est à ce moment que l'étudiant était en salle.
                $refus->created_at,
                'presence.enregistrement_manuel',
                ['scan_refuse_id' => $refus->id, 'raison_refus' => $refus->type],
                $refus->metadata['device_fingerprint'] ?? null,
                fn (Presence $presence) => $refus->update([
                    'resolved'    => true,
                    'resolved_at' => now(),
                    'metadata'    => ($refus->metadata ?? []) + ['presence_id' => $presence->id],
                ]),
            );
        } catch (PresenceManuelleImpossible $refusSaisie) {
            return $this->errorResponse($refusSaisie->getMessage(), $refusSaisie->statutHttp);
        }

        return $this->createdResponse([
            'presence' => ['id' => $presence->id, 'statut' => $presence->statut, 'heure_scan' => $presence->heure_scan],
        ], 'Présence enregistrée.');
    }

    /**
     * Présences des étudiants refusés aux séances visées, en une requête.
     *
     * @param  array<int, array<string, mixed>>  $seances  séance par identifiant d'anomalie
     */
    private function presencesDesRefus(Collection $refus, array $seances): Collection
    {
        $idsSeances = collect($seances)->pluck('id')->unique()->values();

        if ($idsSeances->isEmpty()) {
            return collect();
        }

        return Presence::whereIn('evenement_id', $idsSeances)
            ->whereIn('etudiant_id', $refus->pluck('etudiant_id')->filter()->unique()->values())
            ->get(['id', 'etudiant_id', 'evenement_id', 'statut'])
            ->keyBy(fn (Presence $p) => $p->etudiant_id . '|' . $p->evenement_id);
    }

    /**
     * Séance visée par chaque refus, en deux requêtes pour toute la page.
     *
     * L'identifiant de la séance est noté au moment du refus. Pour les refus
     * enregistrés avant, seul le second scan permet de la retrouver, par la
     * première présence de l'étudiant.
     *
     * @return array<int, array<string, mixed>> séance par identifiant d'anomalie
     */
    private function seancesVisees(Collection $refus): array
    {
        $premieres = Presence::withTrashed()
            ->whereIn('id', $refus->map(fn (Anomaly $a) => $a->metadata['premiere_presence_id'] ?? null)->filter()->unique()->values())
            ->pluck('evenement_id', 'id');

        $evenementDe = $refus->mapWithKeys(fn (Anomaly $a) => [
            $a->id => $a->metadata['evenement_id'] ?? $premieres->get($a->metadata['premiere_presence_id'] ?? 0),
        ])->filter();

        $evenements = Evenement::with('ec:id,code,intitule')
            ->whereIn('id', $evenementDe->unique()->values())
            ->get()
            ->keyBy('id');

        return $evenementDe
            ->map(function ($id) use ($evenements) {
                $seance = $evenements->get($id);

                return $seance ? [
                    'id'          => $seance->id,
                    'date'        => $seance->date->format('Y-m-d'),
                    'heure_debut' => substr((string) $seance->heure_debut, 0, 5),
                    'heure_fin'   => substr((string) $seance->heure_fin, 0, 5),
                    'cours'       => $seance->ec?->intitule,
                    'code'        => $seance->ec?->code,
                ] : null;
            })
            ->filter()
            ->all();
    }
}
