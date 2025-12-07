<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Models\member;
use App\Models\Presence;
use App\Models\DeviceVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrGenerator;

class QrCodeController extends Controller
{
    protected $geofenceService;
    protected $anomalyService;

    public function __construct(
        \App\Services\GeofenceService $geofenceService,
        \App\Services\AnomalyDetectionService $anomalyService
    ) {
        $this->geofenceService = $geofenceService;
        $this->anomalyService = $anomalyService;
    }

    public function generate(Request $request)
    {
        // ... (code existant)
        $userGroup = Auth::user()->group;
        
        // Utiliser le code basé sur le temps avec le groupe
        $timeBasedCode = QrCode::generateTimeBasedCode($userGroup);
        
        $qrCode = QrCode::updateOrCreate(
            [
                'event_date' => $request->date ?? today(),
                'created_by' => Auth::id(),
                'group' => $userGroup
            ],
            [
                'code' => $timeBasedCode,
                'event_name' => $request->event_name,
                'expires_at' => now()->addHours(1),
                'is_active' => true,
            ]
        );

        $url = route('qr.scan', $qrCode->code);
        $qrImage = QrGenerator::size(300)->generate($url);

        return view('qr.generate', compact('qrCode', 'qrImage'));
    }

    // ... (autres méthodes scan, refresh)

    public function markPresence(Request $request, $code)
    {
        // ... (validations existantes)
        $qrCode = QrCode::where('code', $code)->first();

        if (!$qrCode || !$qrCode->isValid()) {
            return response()->json(['error' => 'QR Code invalide ou expiré'], 400);
        }

        $request->validate([
            'phone' => 'required|string',
            'signature' => 'nullable|string',
            'device_fingerprint' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'accuracy' => 'nullable|numeric'
        ]);

        // Vérifier la géolocalisation si disponible
        if ($request->latitude && $request->longitude) {
            $geoResult = $this->geofenceService->isLocationValid(
                $qrCode, 
                (float)$request->latitude, 
                (float)$request->longitude
            );

            if (!$geoResult['valid']) {
                return response()->json([
                    'error' => "Vous êtes trop loin du lieu de l'événement ({$geoResult['distance']}m). Rayon autorisé: {$geoResult['radius']}m."
                ], 403);
            }
        }

        // Vérifier si l'appareil peut faire une vérification (limite 2h)
        if (!DeviceVerification::canVerify($request->device_fingerprint, $request->ip())) {
            return response()->json(['error' => 'Cet appareil a déjà vérifié une présence dans les 2 dernières heures'], 429);
        }

        // Trouver le membre par son numéro de téléphone
        $member = member::where('phone', $request->phone)->first();
        if (!$member) {
            return response()->json(['error' => 'Aucun membre trouvé avec ce numéro de téléphone'], 400);
        }

        // Vérifier si le membre peut utiliser ce QR code (même groupe)
        if (!$qrCode->canBeUsedByMember($member)) {
            return response()->json(['error' => 'Ce QR code n\'est pas valide pour votre groupe'], 403);
        }

        // Enregistrer la vérification de l'appareil
        DeviceVerification::recordVerification($request->device_fingerprint, $request->ip());

        $presence = Presence::updateOrCreate(
            [
                'member_id' => $member->id,
                'date' => $qrCode->event_date
            ],
            [
                'time' => now(),
                'qr_code_id' => $qrCode->code,
                'verification_method' => 'qr_code',
                'signature' => $request->signature,
                'signed_at' => $request->signature ? now() : null,
                'location_data' => [
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'accuracy' => $request->accuracy
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]
        );

        // Vérifier les anomalies (en arrière-plan idéalement, mais ici synchrone pour la démo)
        $this->anomalyService->checkAnomalies($member, [
            'device_fingerprint' => $request->device_fingerprint,
            'ip_address' => $request->ip(),
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'user_agent' => $request->userAgent()
        ]);

        return response()->json(['success' => true, 'message' => 'Présence enregistrée pour ' . $member->name]);
    }
}
