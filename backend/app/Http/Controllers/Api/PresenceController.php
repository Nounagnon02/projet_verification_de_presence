<?php

namespace App\Http\Controllers\Api;

use App\Actions\Gamification\CheckWeeklyAttendance;
use App\Http\Controllers\Controller;
use App\Models\Anomaly;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Presence;
use App\Models\QrCode;
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

        return $this->successResponse([
            'cours'       => $evenement->ec?->intitule ?? 'Cours',
            'heure_debut' => $evenement->heure_debut,
            'heure_fin'   => $evenement->heure_fin,
            'salle'       => $evenement->salle,
            'date'        => $evenement->date?->format('Y-m-d'),
            'filiere'     => $evenement->filiere?->code ?? '',
            'token'       => $token,
        ]);
    }

    /**
     * Valide le scan d'un étudiant et enregistre sa présence.
     * Conforme CDC US04 & US06 : validation QR code + détection fraude.
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
            'latitude'           => 'nullable|numeric|between:-90,90',
            'longitude'          => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        //-------------------------------------------------------------
        // 2. Vérification du QR Code (CDC 7.4.2 & 9.2.1)
        //-------------------------------------------------------------
        $qrCode = QrCode::where('token', $request->token)
            ->where('actif', true)
            ->first();

        if (!$qrCode || $qrCode->isExpired()) {
            return $this->goneResponse('QR Code expiré ou invalide. Veuillez rescanner.');
        }

        // Invalidation immédiate du token (anti-rejeu, CDC 9.2.1)
        $qrCode->update(['actif' => false]);

        //-------------------------------------------------------------
        // 3. Vérification de la fenêtre horaire (CDC 7.3.3)
        //-------------------------------------------------------------
        $evenement = $qrCode->evenement;
        $now = Carbon::now();

        $debut = Carbon::parse($evenement->date->format('Y-m-d') . ' ' . $evenement->heure_debut);
        $fin   = Carbon::parse($evenement->date->format('Y-m-d') . ' ' . $evenement->heure_fin)->addMinutes(15);

        // Gestion du chevauchement de minuit : si l'heure de fin est <= l'heure de début,
        // c'est que la séance traverse minuit, on décale d'un jour.
        if ($fin->lte($debut)) {
            $fin->addDay();
        }

        if ($now->lt($debut) || $now->gt($fin)) {
            return $this->forbiddenResponse('Fenêtre de validation fermée (hors horaire).');
        }

        //-------------------------------------------------------------
        // 4. Identification de l'étudiant (CDC 7.1.3)
        //-------------------------------------------------------------
        $etudiant = Etudiant::where('identifiant_unique', $request->identifiant_unique)->first();

        if (!$etudiant) {
            return $this->notFoundResponse('Identifiant étudiant inconnu.');
        }

        //-------------------------------------------------------------
        // 5. Vérification inscription au cours (CDC 7.2.3 & 7.4.2)
        //    On vérifie d'abord la table pivot etudiant_ec.
        //    Si l'étudiant n'a encore aucune inscription (backfill pas encore fait),
        //    on vérifie uniquement la filière (comportement précédent).
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
        // 6. Détection de double scan et fraude (CDC 9.2.2 & 9.2.3)
        //-------------------------------------------------------------
        $existing = Presence::where('etudiant_id', $etudiant->id)
            ->where('evenement_id', $evenement->id)
            ->first();

        if ($existing) {
            // Même étudiant, appareil différent → fraude potentielle
            // Note : la présence originale reste 'valide'. Seule la tentative frauduleuse est bloquée.
            if ($existing->device_fingerprint !== $request->device_fingerprint) {

                Anomaly::create([
                    'etudiant_id' => $etudiant->id,
                    'type'        => 'double_scan_device_mismatch',
                    'description' => "Fraude suspectée : l'étudiant {$etudiant->nom} {$etudiant->prenom} " .
                        "a déjà scanné l'événement #{$evenement->id} avec un appareil différent.",
                    'severity' => 'high',
                    'metadata' => [
                        'premier_device'  => $existing->device_fingerprint,
                        'nouveau_device'  => $request->device_fingerprint,
                        'premiere_presence_id' => $existing->id,
                    ],
                ]);

                return $this->conflictResponse('Alerte fraude : présence déjà enregistrée depuis un autre appareil.');
            }

            return $this->conflictResponse('Présence déjà enregistrée.');
        }

        //-------------------------------------------------------------
        // 7. Enregistrement de la présence
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
        // 8. Régénération immédiate du QR Code (CDC 9.2.1)
        //    Après chaque scan, un nouveau token est généré pour l'événement.
        //-------------------------------------------------------------
        QrCode::create([
            'evenement_id' => $evenement->id,
            'token'        => (string) Str::uuid(),
            'expire_at'    => Carbon::now()->addSeconds(60),
            'actif'        => true,
        ]);

        //-------------------------------------------------------------
        // 9. Gamification : vérification de la semaine parfaite (CDC 12.1)
        //-------------------------------------------------------------
        $gamification = app(CheckWeeklyAttendance::class)->execute($etudiant);

        return $this->createdResponse([
            'etudiant'     => "{$etudiant->nom} {$etudiant->prenom}",
            'matricule'    => $etudiant->matricule,
            'heure'        => $presence->heure_scan->format('H:i:s'),
            'cours'        => $evenement->ec->intitule ?? 'N/A',
            'gamification' => $gamification['perfect'] ? [
                'perfect_week'   => true,
                'points_awarded' => $gamification['points_awarded'],
            ] : null,
        ], 'Présence enregistrée avec succès.');
    }
}
