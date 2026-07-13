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
        $qrCode = QrCode::where('token', $token)
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
        //-------------------------------------------------------------
        // 1. Validation des entrées
        //-------------------------------------------------------------
        $validator = Validator::make($request->all(), [
            'identifiant_unique' => 'required|string',
            'token'              => 'required|uuid',
            'device_fingerprint' => 'required|string',
            'scan_challenge'     => 'nullable|string', // Challenge cryptographique optionnel
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
        $qrCode = QrCode::where('token', $request->token)
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
        //-------------------------------------------------------------
        $debut = Carbon::parse($evenement->date->format('Y-m-d') . ' ' . $evenement->heure_debut);
        $fin   = Carbon::parse($evenement->date->format('Y-m-d') . ' ' . $evenement->heure_fin)->addMinutes(15);

        if ($fin->lte($debut)) {
            $fin->addDay();
        }

        if ($now->lt($debut) || $now->gt($fin)) {
            return $this->forbiddenResponse('Fenêtre de validation fermée (hors horaire).');
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
        $hasEnrollments = $etudiant->ecs()->exists();

        if ($hasEnrollments) {
            $isEnrolledInEc = $etudiant->ecs()
                ->where('ec_id', $evenement->ec_id)
                ->wherePivot('annee_id', $etudiant->annee_id)
                ->exists();

            if (!$isEnrolledInEc) {
                return $this->forbiddenResponse('Étudiant non inscrit à ce cours.');
            }
        } elseif ($etudiant->filiere_id !== $evenement->filiere_id) {
            return $this->forbiddenResponse('Étudiant non inscrit à ce cours.');
        }

        //-------------------------------------------------------------
        // 6. VÉRIFICATION DEVICE FINGERPRINT + CHALLENGE (Anti-fraude)
        //-------------------------------------------------------------
        // Vérifier le challenge cryptographique si fourni
        if ($request->filled('scan_challenge')) {
            $challengeValid = $this->verifyScanChallenge(
                $request->scan_challenge,
                $request->device_fingerprint
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
                    $distance = round($verificationLog['distance_metres'] ?? 0);
                    $raisons[] = "GPS hors zone (distance: {$distance}m, max: {$salle->rayon_geofence_m}m)";
                }
                if (!$wifiOk && $wifiRequis) {
                    $raisons[] = "Réseau WiFi non conforme (attendu: {$salle->ssid_attendu})";
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
        $existing = Presence::where('etudiant_id', $etudiant->id)
            ->where('evenement_id', $evenement->id)
            ->first();

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
        // 9. Enregistrement de la présence
        //-------------------------------------------------------------
        $presence = Presence::create([
            'etudiant_id'       => $etudiant->id,
            'evenement_id'      => $evenement->id,
            'heure_scan'        => $now,
            'device_fingerprint' => $request->device_fingerprint,
            'ip_address'        => $request->ip(),
            'statut'            => 'valide',
            'latitude'          => $request->latitude,
            'longitude'         => $request->longitude,
        ]);

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
