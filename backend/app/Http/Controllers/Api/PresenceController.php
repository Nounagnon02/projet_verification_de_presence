<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Anomaly;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Presence;
use App\Models\QrCode;
use App\Models\Salle;
use App\Traits\ScopedByEtablissement;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PresenceController extends Controller
{
    use ScopedByEtablissement;

    /**
     * Récupère les informations du cours associé à un token QR (public).
     * Conforme CDC 7.4.1 : le QR code redirige vers un formulaire avec les infos du cours.
     *
     * GET /api/presence/course-by-token/{token}
     */
    public function courseByToken(string $token): JsonResponse
    {
        // La colonne « token » est de type uuid en base : interroger Postgres
        // avec une valeur qui n'en est pas un leve une erreur de syntaxe SQL,
        // et l'endpoint — public, non authentifie — repondait 500 en divulguant
        // le type d'erreur applicative. Releve par la passe OWASP ZAP du
        // 2026-08-24 (regles 100000 et 90022).
        //
        // Un jeton mal forme n'existe pas : la reponse est donc 404, comme pour
        // un jeton inconnu, et sans rien apprendre a l'appelant.
        if (!Str::isUuid($token)) {
            return $this->notFoundResponse('QR Code invalide ou expiré.');
        }

        // Chargement anticipe des relations lues plus bas. Sans lui, cet endpoint
        // emet CINQ requetes la ou une suffit : le QR Code, puis l'evenement, la
        // salle, l'EC et la filiere, chargees paresseusement une par une.
        // C'est le premier des deux appels de tout scan : le cout est paye par
        // chaque etudiant, a chaque prise de presence.
        $qrCode = QrCode::with(['evenement.salleRef', 'evenement.ec', 'evenement.filiere'])
            ->where('token', $token)
            ->where('actif', true)
            ->where('expire_at', '>', now())
            ->first();

        if (!$qrCode) {
            return $this->notFoundResponse('QR Code invalide ou expiré.');
        }

        $evenement = $qrCode->evenement;
        if (!$evenement) {
            return $this->notFoundResponse('Événement introuvable.');
        }

        $salle = $evenement->salleRef;

        return $this->successResponse([
            'cours'            => $evenement->ec?->intitule ?? 'Cours',
            'heure_debut'      => $evenement->heure_debut,
            'heure_fin'        => $evenement->heure_fin,
            'salle'            => $evenement->salle,
            'date'             => $evenement->date?->format('Y-m-d'),
            'filiere'          => $evenement->filiere?->code ?? '',
            'token'            => $token,
            // Informations de vérification requises côté client
            'verification'     => [
                'gps_requis'    => $salle && $salle->actif && $salle->latitude !== null,
                'wifi_requis'   => $salle && $salle->actif && !$salle->hors_reseau && ($salle->ssid_attendu || $salle->bssid_attendu),
                'nom_salle'     => $salle?->nom ?? $evenement->salle,
            ],
        ]);
    }

    /**
     * Valide le scan d'un étudiant et enregistre sa présence.
     *
     * VÉRIFICATION TRIPLE FACTEUR (CDC US04 & US06) :
     *   1. QR Code valide (facteur visuel)
     *   2. Géolocalisation GPS dans le rayon de la salle
     *   3. Réseau WiFi (SSID/BSSID) correspondant à la salle
     *
     * ANTI-FRAUDE (CDC 9.2) :
     *   - QR Token rotation 60s + invalidation post-scan
     *   - Device fingerprint + challenge cryptographique
     *   - Détection cross-device double-scan
     *
     * POST /api/presence/scan
     */
    public function scan(Request $request): JsonResponse
    {
        // L'étudiant est authentifié : c'est le jeton, et lui seul, qui le
        // désigne (capacité « etudiant », voir routes/api.php). Régression :
        // il était auparavant posté comme « identifiant_unique », une chaîne
        // déterministe (NOM_PRENOM_MATRICULE_FILIERE_ANNEE) que n'importe quel
        // camarade de promotion pouvait reconstituer pour pointer à sa place.
        $validator = Validator::make($request->all(), [
            'token'              => 'required|uuid',
            'device_fingerprint' => 'required|string',
            'latitude'           => 'nullable|numeric|between:-90,90',
            'longitude'          => 'nullable|numeric|between:-180,180',
            'ssid'               => 'nullable|string|max:255',
            'bssid'              => 'nullable|string|max:17', // Format MAC: 00:11:22:33:44:55
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $demande = new \App\Services\Presence\DemandeDeScan(
            etudiant: $request->user(),
            jeton: $request->string('token')->toString(),
            empreinteAppareil: $request->string('device_fingerprint')->toString(),
            latitude: $request->float('latitude') ?: null,
            longitude: $request->float('longitude') ?: null,
            ssid: $request->string('ssid')->toString() ?: null,
            bssid: $request->string('bssid')->toString() ?: null,
            ip: $request->ip(),
        );

        $resultat = (new \App\Services\Presence\EnregistrementPresence())->traiter($demande);

        return $resultat->accepte
            ? $this->successResponse($resultat->donnees, $resultat->message, $resultat->statutHttp)
            : $this->errorResponse($resultat->message, $resultat->statutHttp);
    }

    /**
     * Validation manuelle d'une présence par un administrateur.
     *
     * Permet à un admin (enseignant, chef département, scolarité) de valider
     * ou rejeter une présence suspecte ou non scannée.
     *
     * POST /api/admin/presence/{id}/validate
     */
    public function validateManual(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:valider,rejeter',
            'motif'  => 'required_if:action,rejeter|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $presence = Presence::with(['etudiant', 'evenement.ec', 'evenement.filiere'])->find($id);

        if (!$presence) {
            return $this->notFoundResponse('Présence introuvable.');
        }

        // Vérifier les permissions (le middleware vérifie le rôle, ici on vérifie le périmètre)
        $user = $request->user();
        if ($user->isFaculteAdmin() && $user->etablissement_id !== $presence->etudiant->filiere->etablissement_id) {
            return $this->forbiddenResponse('Vous n\'êtes pas autorisé à valider cette présence.');
        }

        $action = $request->action;
        $motif = $request->motif ?? null;

        // Lu avant la mise à jour : c'est lui que le journal doit conserver.
        $ancienStatut = $presence->statut;

        if ($action === 'valider') {
            if ($presence->statut === 'valide') {
                return $this->conflictResponse('Cette présence est déjà validée.');
            }

            $presence->update([
                'statut' => 'valide',
                'validated_by' => $user->id,
                'validated_at' => now(),
                'validation_motif' => $motif,
            ]);

            $this->fermerAlertesAppareilPartage($presence);

            // logAudit n'enregistre que old_values et new_values. Les clés
            // old_status et new_status passées jusqu'ici étaient ignorées : le
            // journal ne gardait aucun statut, et l'ancien était écrit en dur.
            $this->logAudit('presence.validate_manual', $presence, $user, [
                'old_values' => ['statut' => $ancienStatut],
                'new_values' => ['statut' => 'valide', 'motif' => $motif],
            ]);

            return $this->successResponse([
                'presence' => $presence->load('etudiant'),
                'message' => 'Présence validée manuellement.',
            ], 'Présence validée avec succès.');
        }

        if ($action === 'rejeter') {
            if ($presence->statut === 'rejete') {
                return $this->conflictResponse('Cette présence est déjà rejetée.');
            }

            $presence->update([
                'statut' => 'rejete',
                'validated_by' => $user->id,
                'validated_at' => now(),
                'validation_motif' => $motif,
            ]);

            $this->fermerAlertesAppareilPartage($presence);

            // Créer une entrée d'audit
            $this->logAudit('presence.reject_manual', $presence, $user, [
                'old_values' => ['statut' => $ancienStatut],
                'new_values' => ['statut' => 'rejete', 'motif' => $motif],
            ]);

            return $this->successResponse([
                'presence' => $presence->load('etudiant'),
                'message' => 'Présence rejetée.',
            ], 'Présence rejetée avec succès.');
        }

        return $this->validationErrorResponse(['action' => ['Action invalide.']]);
    }

    /**
     * File des présences à arbitrer.
     *
     * Seul le scan en produit, pour un seul motif : un même téléphone utilisé
     * par plusieurs étudiants pendant une séance (statut « suspect »). Les
     * statuts « en_attente » et « invalide » que cette file acceptait n'étaient
     * écrits nulle part.
     *
     * Chaque ligne porte « meme_appareil » : les autres scans du même téléphone
     * pour la même séance. C'est la raison de la suspicion, et l'administrateur
     * doit voir le groupe entier pour trancher ; les lignes d'un groupe se
     * suivent.
     *
     * GET /api/admin/presence/pending
     */
    public function pendingValidations(Request $request): JsonResponse
    {
        $query = Presence::with(['etudiant.filiere', 'evenement.ec', 'evenement.filiere', 'evenement.salleRef'])
            ->where('statut', 'suspect');

        // Un administrateur de faculté ne voit que ses étudiants. La file
        // n'appliquait aucun cloisonnement : elle exposait les noms et
        // matricules des autres établissements.
        if ($etablissementId = $this->getEtablissementId($request)) {
            $query->whereHas('etudiant.filiere', fn ($q) => $q->where('etablissement_id', $etablissementId));
        }

        // Filtres
        if ($request->filled('filiere_id')) {
            $query->whereHas('etudiant', fn ($q) => $q->where('filiere_id', $request->filiere_id));
        }

        if ($request->filled('evenement_id')) {
            $query->where('evenement_id', $request->evenement_id);
        }

        if ($request->filled('date_from')) {
            $query->whereHas('evenement', fn ($q) => $q->whereDate('date', '>=', $request->date_from));
        }

        if ($request->filled('date_to')) {
            $query->whereHas('evenement', fn ($q) => $q->whereDate('date', '<=', $request->date_to));
        }

        // Recherche côté serveur : faite dans le navigateur, elle ne portait que
        // sur la page affichée.
        if ($request->filled('search')) {
            $terme = '%' . addcslashes(trim((string) $request->search), '%_\\') . '%';
            $query->whereHas('etudiant', fn ($q) => $q->where(fn ($q) => $q
                ->where('nom', 'ilike', $terme)
                ->orWhere('prenom', 'ilike', $terme)
                ->orWhere('matricule', 'ilike', $terme)
                ->orWhereRaw("prenom || ' ' || nom ilike ?", [$terme])));
        }

        // Séances les plus récentes d'abord ; dans une séance, les scans d'un
        // même téléphone se suivent, dans l'ordre où ils ont eu lieu.
        $query->orderByDesc(Evenement::select('date')->whereColumn('evenements.id', 'presences.evenement_id'))
            ->orderBy('evenement_id')
            ->orderBy('device_fingerprint')
            ->orderBy('heure_scan')
            // À la seconde près, les scans d'un même téléphone tombent souvent
            // ensemble : l'identifiant garde leur ordre d'arrivée.
            ->orderBy('id');

        // Pagination
        $perPage = min($request->integer('per_page', 20), 100);
        $presences = $query->paginate($perPage);

        $this->joindreMemeAppareil($presences->getCollection());

        return $this->successResponse($presences);
    }

    /**
     * Ajoute à chaque présence les autres scans du même téléphone pour la même
     * séance, rejetés ou validés compris, en une requête pour toute la page.
     */
    private function joindreMemeAppareil(\Illuminate\Support\Collection $presences): void
    {
        if ($presences->isEmpty()) {
            return;
        }

        $groupes = Presence::with('etudiant:id,nom,prenom,matricule')
            ->whereIn('evenement_id', $presences->pluck('evenement_id')->unique()->values())
            ->whereIn('device_fingerprint', $presences->pluck('device_fingerprint')->filter()->unique()->values())
            ->orderBy('heure_scan')
            ->orderBy('id')
            ->get(['id', 'etudiant_id', 'evenement_id', 'device_fingerprint', 'heure_scan', 'statut'])
            ->groupBy(fn (Presence $p) => $p->evenement_id . '|' . $p->device_fingerprint);

        foreach ($presences as $presence) {
            $voisins = $groupes->get($presence->evenement_id . '|' . $presence->device_fingerprint, collect())
                ->reject(fn (Presence $p) => $p->id === $presence->id)
                ->map(fn (Presence $p) => [
                    'id'         => $p->id,
                    'etudiant'   => $p->etudiant?->only(['id', 'nom', 'prenom', 'matricule']),
                    'heure_scan' => $p->heure_scan?->toIso8601String(),
                    'statut'     => $p->statut,
                ])
                ->values()
                ->all();

            $presence->setAttribute('meme_appareil', $voisins);
        }
    }

    /**
     * Ferme l'alerte « appareil partagé » d'une présence qui vient d'être
     * arbitrée : la décision est prise, l'alerte n'a plus rien à signaler.
     * Elle restait sinon ouverte indéfiniment.
     */
    private function fermerAlertesAppareilPartage(Presence $presence): void
    {
        Anomaly::where('type', 'appareil_partage')
            ->where('etudiant_id', $presence->etudiant_id)
            // Chaîne : ->> renvoie du texte, que PostgreSQL ne compare pas à un entier.
            ->where('metadata->evenement_id', (string) $presence->evenement_id)
            ->where('resolved', false)
            ->update(['resolved' => true, 'resolved_at' => now()]);
    }

    /**
     * Logger une action d'audit
     */
    private function logAudit(string $action, $model, $user, array $changes = []): void
    {
        \App\Models\AuditLog::create([
            'action'      => $action,
            'model_type'  => get_class($model),
            'model_id'    => $model->id,
            'user_id'     => $user->id,
            'old_values'  => $changes['old_values'] ?? null,
            'new_values'  => $changes['new_values'] ?? null,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);
    }

    /**
     * Historique des présences de l'étudiant connecté.
     * GET /api/presence/my-history
     */
    public function myHistory(Request $request): JsonResponse
    {
        $etudiant = Etudiant::where('email', $request->user()->email)->first();

        if (!$etudiant) {
            return response()->json(['data' => []], 200);
        }

        $perPage = min((int) $request->input('per_page', 20), 50);
        $presences = Presence::with(['evenement.ec'])
            ->where('etudiant_id', $etudiant->id)
            ->latest('heure_scan')
            ->paginate($perPage);

        return response()->json($presences);
    }

    /**
     * Statistiques de présence de l'étudiant connecté.
     * GET /api/presence/my-stats
     */
    public function myStats(Request $request): JsonResponse
    {
        $etudiant = Etudiant::where('email', $request->user()->email)->first();

        if (!$etudiant) {
            return $this->errorResponse('Profil étudiant introuvable.', 404);
        }

        $total = Presence::where('etudiant_id', $etudiant->id)->count();
        $validees = Presence::where('etudiant_id', $etudiant->id)
            ->where('statut', 'valide')->count();
        $rejetees = Presence::where('etudiant_id', $etudiant->id)
            ->where('statut', 'rejete')->count();
        $enAttente = Presence::where('etudiant_id', $etudiant->id)
            ->whereIn('statut', ['en_attente', 'suspect'])->count();

        return $this->successResponse([
            'total'           => $total,
            'validees'         => $validees,
            'rejetees'         => $rejetees,
            'en_attente'       => $enAttente,
            'taux_validation'  => $total > 0
                ? round($validees / $total * 100, 1)
                : 0,
        ]);
    }
}
