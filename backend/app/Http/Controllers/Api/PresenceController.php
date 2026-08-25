<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Anomaly;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Presence;
use App\Models\QrCode;
use App\Models\Salle;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class PresenceController extends Controller
{
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
            // Défi anti-fraude que le client doit renvoyer tel quel au scan.
            // Il est signé avec la clé du serveur et lié au jeton du QR Code :
            // il hérite donc de son usage unique et de son TTL de 60 s, et il
            // ne peut pas être fabriqué par un client. Aucun secret serveur ne
            // quitte le serveur — seule la signature circule.
            'scan_challenge'   => $this->scanChallengeFor($token),
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
        //-------------------------------------------------------------
        // 1. Validation des entrées
        //-------------------------------------------------------------
        $validator = Validator::make($request->all(), [
            'identifiant_unique' => 'required|string',
            'token'              => 'required|uuid',
            'device_fingerprint' => 'required|string',
            'scan_challenge'     => 'required|string', // Challenge cryptographique anti-fraude
            // Géolocalisation
            'latitude'           => 'nullable|numeric|between:-90,90',
            'longitude'          => 'nullable|numeric|between:-180,180',
            // Réseau WiFi
            'ssid'               => 'nullable|string|max:255',
            'bssid'              => 'nullable|string|max:17', // Format MAC: 00:11:22:33:44:55
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        //-------------------------------------------------------------
        // 2. Vérification du QR Code (Facteur 1 — Visuel)
        //-------------------------------------------------------------
        // Meme raison que dans courseByToken : l'evenement, sa salle et son EC
        // sont tous lus plus bas. Les charger paresseusement coutait trois
        // allers-retours supplementaires a chaque scan.
        $qrCode = QrCode::with(['evenement.salleRef', 'evenement.ec'])
            ->where('token', $request->token)
            ->where('actif', true)
            ->first();

        if (!$qrCode || $qrCode->isExpired()) {
            return $this->goneResponse('QR Code expiré ou invalide. Veuillez rescanner.');
        }

        // Invalidation immédiate du token (anti-rejeu, CDC 9.2.1)
        $qrCode->update(['actif' => false]);

        $evenement = $qrCode->evenement;
        $now = Carbon::now();

        //-------------------------------------------------------------
        // 3. Vérification de la fenêtre horaire (CDC 7.3.3)
        //
        // La fenêtre est ancrée sur la fin du cours (config/presence.php) et
        // calculée par le modèle. Elle ne s'ouvre plus à l'heure de début :
        // signer en début de séance puis repartir ne permet plus d'être compté
        // présent.
        //-------------------------------------------------------------
        $ouverture = $evenement->ouvertureScan();
        $fermeture = $evenement->fermetureScan();

        if ($now->lessThan($ouverture)) {
            return $this->forbiddenResponse(
                "La prise de présence n'est pas encore ouverte. Elle le sera à partir de "
                . $ouverture->format('H:i') . '.'
            );
        }

        if ($now->greaterThan($fermeture)) {
            return $this->forbiddenResponse(
                'La prise de présence est terminée depuis ' . $fermeture->format('H:i') . '.'
            );
        }

        //-------------------------------------------------------------
        // 4. Identification de l'étudiant
        //-------------------------------------------------------------
        $etudiant = Etudiant::where('identifiant_unique', $request->identifiant_unique)->first();

        if (!$etudiant) {
            return $this->notFoundResponse('Identifiant étudiant inconnu.');
        }

        //-------------------------------------------------------------
        // 5. Vérification inscription au cours
        //-------------------------------------------------------------
        // L'inscription a CET EC est verifiee en premier : dans le cas nominal —
        // l'etudiant est bien inscrit — une seule requete suffit. L'ordre
        // inverse en emettait systematiquement deux, y compris quand la reponse
        // etait evidente.
        $inscritACetEc = $etudiant->ecs()
            ->where('ec_id', $evenement->ec_id)
            ->wherePivot('annee_id', $etudiant->annee_id)
            ->exists();

        if (!$inscritACetEc) {
            // Deux situations a distinguer : l'etudiant a des inscriptions mais
            // pas a ce cours (refus), ou il n'en a aucune et l'on retombe alors
            // sur son rattachement de filiere.
            $aDesInscriptions = $etudiant->ecs()->exists();

            if ($aDesInscriptions || $etudiant->filiere_id !== $evenement->filiere_id) {
                return $this->forbiddenResponse('Étudiant non inscrit à ce cours.');
            }
        }

        //-------------------------------------------------------------
        // 6. VÉRIFICATION DEVICE FINGERPRINT + CHALLENGE (Anti-fraude)
        //-------------------------------------------------------------
        // Le défi est lié au jeton du QR Code, pas à l'appareil : il atteste que
        // le client a bien récupéré ce QR auprès du serveur avant de soumettre.
        if ($request->filled('scan_challenge')) {
            $challengeValid = $this->verifyScanChallenge(
                $request->scan_challenge,
                $request->token
            );

            if (!$challengeValid) {
                Anomaly::create([
                    'etudiant_id' => $etudiant->id,
                    'type'        => 'invalid_scan_challenge',
                    'description' => "Challenge de scan invalide pour {$etudiant->nom} {$etudiant->prenom}. Tentative de contournement possible.",
                    'severity'   => 'high',
                    'metadata'   => [
                        'challenge_recu'   => $request->scan_challenge,
                        'device_fingerprint' => $request->device_fingerprint,
                    ],
                ]);

                return $this->forbiddenResponse('Échec de la vérification de sécurité. Veuillez réessayer.');
            }
        }

        //-------------------------------------------------------------
        // 7. VÉRIFICATION TRIPLE FACTEUR — Localisation + Réseau
        //-------------------------------------------------------------
        $salle = $evenement->salleRef;
        $verificationLog = [
            'qr_valide'   => true,
            'gps_valide'  => null,
            'wifi_valide' => null,
            'ip_valide'   => null,
            'mode'        => 'basique', // par défaut
        ];

        if ($salle && $salle->actif) {
            $verificationLog['salle_id']   = $salle->id;
            $verificationLog['salle_nom']  = $salle->nom;
            $verificationLog['mode']       = 'strict';

            // --- Facteur 2 : Géolocalisation GPS ---
            if ($salle->latitude !== null && $salle->longitude !== null) {
                $verificationLog['gps_valide'] = $salle->isWithinGeofence(
                    $request->latitude,
                    $request->longitude
                );
                $verificationLog['distance_metres'] = $salle->distanceMetres(
                    $request->latitude,
                    $request->longitude
                );
            } else {
                // Salle sans GPS configuré → on skip
                $verificationLog['gps_valide'] = 'non_config';
            }

            // --- Facteur 3 : Réseau WiFi (SSID/BSSID) ---
            if (!$salle->hors_reseau && ($salle->ssid_attendu || $salle->bssid_attendu)) {
                $verificationLog['wifi_valide'] = $salle->matchesWifi(
                    $request->ssid,
                    $request->bssid
                );
                $verificationLog['ssid_recu']  = $request->ssid;
                $verificationLog['bssid_recu'] = $request->bssid;
            } else {
                $verificationLog['wifi_valide'] = 'non_config';
            }

            // --- Vérification IP ---
            $verificationLog['ip_valide'] = $salle->matchesIpRange($request->ip());

            // --- Décision : mode strict vs tolérance ---
            $gpsCheck  = $verificationLog['gps_valide'];
            $wifiCheck = $verificationLog['wifi_valide'];

            // Déterminer si GPS est requis (non null et non 'non_config')
            $gpsRequis    = $gpsCheck !== null && $gpsCheck !== 'non_config';
            $wifiRequis   = $wifiCheck !== null && $wifiCheck !== 'non_config';

            $gpsOk   = !$gpsRequis  || $gpsCheck === true;
            $wifiOk  = !$wifiRequis || $wifiCheck === true;

            // Les DEUX facteurs doivent être OK si configurés
            if (!$gpsOk || !$wifiOk) {
                $raisons = [];
                if (!$gpsOk && $gpsRequis) {
                    // Distinguer « position absente » de « position hors zone ».
                    // Sans cela, distanceMetres() renvoyant null s'interpolait en
                    // « 0m » et l'étudiant lisait « distance : 0m, max : 50m » —
                    // un refus qui se contredit lui-même, et qui ne lui dit pas
                    // que c'est l'autorisation de géolocalisation qui manque.
                    $distance = $verificationLog['distance_metres'] ?? null;

                    $raisons[] = $distance === null
                        ? "Position non transmise : autorisez la géolocalisation, puis réessayez."
                        : 'Vous êtes à ' . round($distance) . ' m de la salle (rayon autorisé : '
                          . $salle->rayon_geofence_m . ' m).';
                }
                if (!$wifiOk && $wifiRequis) {
                    // Un navigateur ne peut pas lire le nom du réseau : seule
                    // l'application mobile transmet ce champ. Le message doit donc
                    // orienter vers elle plutôt que de laisser l'étudiant chercher
                    // ce qu'il a mal fait.
                    $raisons[] = $request->filled('ssid') || $request->filled('bssid')
                        ? "Réseau Wi-Fi non conforme : connectez-vous à « {$salle->ssid_attendu} »."
                        : "Réseau Wi-Fi non transmis : cette salle exige une validation depuis l'application mobile.";
                }

                // Enregistrer l'anomalie
                Anomaly::create([
                    'etudiant_id' => $etudiant->id,
                    'type'        => 'verification_echouee',
                    'description' => "Vérification localisation/réseau échouée pour {$etudiant->nom} {$etudiant->prenom} " .
                        "— salle {$salle->nom} — " . implode('; ', $raisons),
                    'severity' => 'medium',
                    'metadata'  => $verificationLog,
                ]);

                return $this->forbiddenResponse(
                    'Vérification de présence échouée. Vous devez être physiquement dans la salle de cours. ' .
                    implode('. ', $raisons) . '.'
                );
            }

            $verificationLog['statut'] = 'ok';
        }
        // Si pas de salle configurée → mode basique (QR seul), loggé

        //-------------------------------------------------------------
        // 8. Détection de double scan et fraude (CDC 9.2.2 & 9.2.3)
        //-------------------------------------------------------------
        // « withTrashed » est indispensable : la contrainte d'unicité SQL porte sur
        // (etudiant_id, evenement_id) SANS tenir compte de deleted_at, alors
        // qu'Eloquent exclut par défaut les lignes supprimées logiquement. Une
        // présence effacée par un administrateur restait donc invisible à ce
        // contrôle tout en bloquant l'insertion : l'étudiant recevait l'exception
        // SQL brute — noms de tables, hôte et base compris — et se trouvait
        // définitivement empêché de scanner ce cours.
        $existing = Presence::withTrashed()
            ->where('etudiant_id', $etudiant->id)
            ->where('evenement_id', $evenement->id)
            ->first();

        // Présence supprimée par un administrateur : le scan la rétablit avec les
        // données du nouveau passage. Refuser reviendrait à priver l'étudiant de
        // toute nouvelle tentative pour ce cours.
        if ($existing && $existing->trashed()) {
            $existing->restore();
            $existing->update([
                'heure_scan'        => $now,
                'device_fingerprint' => $request->device_fingerprint,
                'ip_address'        => $request->ip(),
                'statut'            => 'valide',
                'latitude'          => $request->latitude,
                'longitude'         => $request->longitude,
            ]);

            return $this->successResponse([
                'etudiant'  => "{$etudiant->nom} {$etudiant->prenom}",
                'matricule' => $etudiant->matricule,
                'heure'     => $now->format('H:i:s'),
                'cours'     => $evenement->ec?->intitule ?? 'Cours',
            ], 'Présence enregistrée avec succès.');
        }

        if ($existing) {
            if ($existing->device_fingerprint !== $request->device_fingerprint) {
                Anomaly::create([
                    'etudiant_id' => $etudiant->id,
                    'type'        => 'double_scan_device_mismatch',
                    'description' => "Fraude suspectée : l'étudiant {$etudiant->nom} {$etudiant->prenom} " .
                        "a déjà scanné l'événement #{$evenement->id} avec un appareil différent.",
                    'severity'   => 'high',
                    'metadata'   => [
                        'premier_device'      => $existing->device_fingerprint,
                        'nouveau_device'      => $request->device_fingerprint,
                        'premiere_presence_id' => $existing->id,
                    ],
                ]);

                return $this->conflictResponse('Alerte fraude : présence déjà enregistrée depuis un autre appareil.');
            }

            return $this->conflictResponse('Présence déjà enregistrée.');
        }

        //-------------------------------------------------------------
        // 8 bis. Détection d'appareil partagé (buddy punching)
        //-------------------------------------------------------------
        // Fraude la plus courante : un seul téléphone qui scanne pour toute la
        // classe (chaque étudiant se connecte à tour de rôle). On compte les
        // autres étudiants ayant déjà utilisé CE device pour CET événement.
        // La présence n'est pas bloquée mais marquée « suspect » : elle part
        // en file de validation manuelle, l'administrateur tranche.
        $statut = 'valide';

        // « exists » plutot que « count(distinct) » : dans le cas nominal — aucun
        // appareil partage — la question posee est binaire, et un COUNT DISTINCT
        // parcourt toutes les presences de l'evenement pour rien. Le decompte
        // exact n'est calcule que lorsqu'il sert reellement, c'est-a-dire pour
        // rediger l'anomalie.
        $memeAppareil = Presence::where('evenement_id', $evenement->id)
            ->where('device_fingerprint', $request->device_fingerprint)
            ->where('etudiant_id', '!=', $etudiant->id);

        if ($memeAppareil->exists()) {
            $autresEtudiantsMemeAppareil = (clone $memeAppareil)->distinct()->count('etudiant_id');

            $statut = 'suspect';

            Anomaly::create([
                'etudiant_id' => $etudiant->id,
                'type'        => 'appareil_partage',
                'description' => "Appareil partagé suspecté : le même appareil a déjà servi à "
                    . "{$autresEtudiantsMemeAppareil} autre(s) étudiant(s) pour l'événement "
                    . "#{$evenement->id}. Scan de {$etudiant->nom} {$etudiant->prenom} marqué à vérifier.",
                'severity'    => 'high',
                'metadata'    => [
                    'device_fingerprint'          => $request->device_fingerprint,
                    'evenement_id'                => $evenement->id,
                    'autres_etudiants_meme_device' => $autresEtudiantsMemeAppareil,
                ],
            ]);
        }

        //-------------------------------------------------------------
        // 9. Enregistrement de la présence
        //-------------------------------------------------------------
        // La contrainte d'unicité est le dernier rempart contre deux scans
        // concurrents du même étudiant (CDC 9.2.3). Sans ce filet, sa violation
        // remontait telle quelle au client : l'exception PDO expose le nom de la
        // base, l'hôte, le port et les identifiants internes, sur un endpoint
        // public et non authentifié.
        try {
            $presence = Presence::create([
                'etudiant_id'       => $etudiant->id,
                'evenement_id'      => $evenement->id,
                'heure_scan'        => $now,
                'device_fingerprint' => $request->device_fingerprint,
                'ip_address'        => $request->ip(),
                'statut'            => $statut,
                'latitude'          => $request->latitude,
                'longitude'         => $request->longitude,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return $this->conflictResponse('Présence déjà enregistrée.');
        }

        //-------------------------------------------------------------
        // 10. Régénération immédiate du QR Code (CDC 9.2.1)
        //-------------------------------------------------------------
        QrCode::create([
            'evenement_id' => $evenement->id,
            'token'        => (string) Str::uuid(),
            'expire_at'    => Carbon::now()->addSeconds(60),
            'actif'        => true,
        ]);

        return $this->createdResponse([
            'etudiant'     => "{$etudiant->nom} {$etudiant->prenom}",
            'matricule'    => $etudiant->matricule,
            'heure'        => $presence->heure_scan->format('H:i:s'),
            'cours'        => $evenement->ec->intitule ?? 'N/A',
            'verification' => $verificationLog,
        ], 'Présence enregistrée avec succès.');
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

            // Créer une entrée d'audit
            $this->logAudit('presence.validate_manual', $presence, $user, [
                'old_status' => 'invalide',
                'new_status' => 'valide',
                'motif' => $motif,
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

            // Créer une entrée d'audit
            $this->logAudit('presence.reject_manual', $presence, $user, [
                'old_status' => $presence->statut,
                'new_status' => 'rejete',
                'motif' => $motif,
            ]);

            return $this->successResponse([
                'presence' => $presence->load('etudiant'),
                'message' => 'Présence rejetée.',
            ], 'Présence rejetée avec succès.');
        }

        return $this->validationErrorResponse(['action' => ['Action invalide.']]);
    }

    /**
     * Lister les présences en attente de validation (suspectes ou sans scan)
     *
     * GET /api/admin/presence/pending
     */
    public function pendingValidations(Request $request): JsonResponse
    {
        $query = Presence::with(['etudiant.filiere', 'evenement.ec', 'evenement.filiere', 'evenement.salleRef'])
            ->whereIn('statut', ['suspect', 'en_attente', 'invalide'])
            ->orderBy('created_at', 'desc');

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

        // Pagination
        $perPage = min($request->integer('per_page', 20), 100);
        $presences = $query->paginate($perPage);

        return $this->successResponse($presences);
    }

    /**
     * Défi anti-fraude associé à un jeton de QR Code.
     *
     * Signature HMAC-SHA256 du jeton avec la clé de l'application. La clé reste
     * sur le serveur : le client reçoit la signature via
     * GET /presence/course-by-token/{token} et la renvoie telle quelle au scan.
     *
     * Ce que le défi prouve : le client a bien lu un QR Code valide auprès du
     * serveur avant de soumettre, au lieu de fabriquer une requête de scan. Le
     * jeton étant à usage unique et valable 60 s, la signature l'est aussi.
     *
     * Ce que le défi ne prouve pas, et ne prétend pas prouver : l'identité de
     * l'appareil. Cette garantie-là repose sur le device_fingerprint (détection
     * d'appareil partagé, § 8 bis) et non sur ce champ.
     */
    private function scanChallengeFor(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    /**
     * Vérifie que le défi reçu est bien celui que le serveur a émis pour ce jeton.
     */
    private function verifyScanChallenge(string $challenge, string $token): bool
    {
        return hash_equals($this->scanChallengeFor($token), $challenge);
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
