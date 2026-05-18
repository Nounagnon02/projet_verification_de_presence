<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Presence;
use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PresenceController extends Controller
{
    /**
     * Valide le scan d'un étudiant et enregistre sa présence.
     * CDC US04: Validation de présence via QR Code dynamique.
     */
    public function scan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifiant_unique' => 'required|string',
            'token' => 'required|uuid',
            'device_fingerprint' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 1. Vérifier le token QR Code (CDC 7.4.2 & 9.2.1)
        $qrCode = QrCode::where('token', $request->token)->where('actif', true)->first();
        if (!$qrCode || $qrCode->isExpired()) {
            return response()->json(['message' => 'QR Code périmé, l\'étudiant doit rescanner.'], 410);
        }

        // 2. Invalider immédiatement le token après détection (CDC 9.2.1)
        $qrCode->update(['actif' => false]);

        // 3. Vérifier l'événement et la fenêtre horaire (CDC 7.3.3)
        $evenement = $qrCode->evenement;
        $now = Carbon::now();
        $debut = Carbon::createFromFormat('H:i:s', $evenement->heure_debut, 'UTC')->setDateFrom($evenement->date);
        $fin = Carbon::createFromFormat('H:i:s', $evenement->heure_fin, 'UTC')->setDateFrom($evenement->date)->addMinutes(15);
        
        if ($now->lt($debut) || $now->gt($fin)) {
            return response()->json(['message' => 'Fenêtre de validation impossible (hors horaire).'], 403);
        }

        // 4. Vérifier l'étudiant via identifiant unique déterministe
        $etudiant = Etudiant::where('identifiant_unique', $request->identifiant_unique)->first();
        if (!$etudiant) {
            return response()->json(['message' => 'Identifiant invalide ou inconnu.'], 404);
        }

        // 5. Vérifier si l'étudiant est associé à ce cours (CDC 7.4.2)
        // Note: L'association est censée être automatique via filière/année
        if ($etudiant->filiere_id !== $evenement->filiere_id) {
            return response()->json(['message' => 'Étudiant non inscrit au cours concerné.'], 403);
        }

        // 6. Vérifier la double soumission et fraude (CDC 9.2.2 & 9.2.3)
        $existing = Presence::where('etudiant_id', $etudiant->id)
                            ->where('evenement_id', $evenement->id)
                            ->first();

        if ($existing) {
            // Tentative de fraude : même séance, mais on vérifie le fingerprint (CDC 9.2.2)
            if ($existing->device_fingerprint !== $request->device_fingerprint) {
                // Créer un signalement de fraude (statut suspect)
                $existing->update(['statut' => 'suspect']);
                return response()->json(['message' => 'Alerte fraude : Présence déjà enregistrée depuis un autre appareil.'], 409);
            }
            return response()->json(['message' => 'Présence déjà enregistrée.'], 409);
        }

        // 7. Enregistrement final
        $presence = Presence::create([
            'etudiant_id' => $etudiant->id,
            'evenement_id' => $evenement->id,
            'heure_scan' => $now,
            'device_fingerprint' => $request->device_fingerprint,
            'ip_address' => $request->ip(),
            'statut' => 'valide',
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        return response()->json([
            'message' => 'Présence enregistrée avec succès.',
            'data' => [
                'etudiant' => "{$etudiant->nom} {$etudiant->prenom}",
                'heure' => $presence->heure_scan->format('H:i:s'),
                'cours' => $evenement->ec->intitule
            ]
        ], 201);
    }
}
