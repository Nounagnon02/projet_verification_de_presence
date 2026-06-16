<?php

namespace App\Http\Controllers\Api;

use App\Actions\Gamification\CheckWeeklyAttendance;
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
        // 6. VÉRIFICATION TRIPLE FACTEUR — Localisation + Réseau
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
        // 7. Détection de double scan et fraude (CDC 9.2.2 & 9.2.3)
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
        // 8. Enregistrement de la présence
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
        // 9. Régénération immédiate du QR Code (CDC 9.2.1)
        //-------------------------------------------------------------
        QrCode::create([
            'evenement_id' => $evenement->id,
            'token'        => (string) Str::uuid(),
            'expire_at'    => Carbon::now()->addSeconds(60),
            'actif'        => true,
        ]);

        //-------------------------------------------------------------
        // 10. Gamification : vérification de la semaine parfaite (CDC 12.1)
        //-------------------------------------------------------------
        $gamification = app(CheckWeeklyAttendance::class)->execute($etudiant);

        return $this->createdResponse([
            'etudiant'     => "{$etudiant->nom} {$etudiant->prenom}",
            'matricule'    => $etudiant->matricule,
            'heure'        => $presence->heure_scan->format('H:i:s'),
            'cours'        => $evenement->ec->intitule ?? 'N/A',
            'verification' => $verificationLog,
            'gamification' => $gamification['perfect'] ? [
                'perfect_week'   => true,
                'points_awarded' => $gamification['points_awarded'],
            ] : null,
        ], 'Présence enregistrée avec succès.');
    }
}
