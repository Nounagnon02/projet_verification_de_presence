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
    public function generate(Request $request)
    {
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
                'is_active' => true
            ]
        );

        $url = route('qr.scan', $qrCode->code);
        $qrImage = QrGenerator::size(300)->generate($url);

        return view('qr.generate', compact('qrCode', 'qrImage'));
    }

    public function scan($code)
    {
        $qrCode = QrCode::where('code', $code)->first();

        if (!$qrCode || !$qrCode->isValid()) {
            return redirect()->route('dashboard')->with('error', 'QR Code invalide ou expiré');
        }

        return view('qr.scan', compact('qrCode'));
    }
    
    public function refresh()
    {
        $userGroup = Auth::user()->group;
        $currentCode = QrCode::generateTimeBasedCode($userGroup);
        $url = route('qr.scan', $currentCode);
        $qrImage = QrGenerator::size(300)->generate($url);
        
        return response()->json([
            'code' => $currentCode,
            'qr_image' => base64_encode($qrImage),
            'timestamp' => now()->format('H:i:s')
        ]);
    }

    public function markPresence(Request $request, $code)
    {
        $qrCode = QrCode::where('code', $code)->first();

        if (!$qrCode || !$qrCode->isValid()) {
            return response()->json(['error' => 'QR Code invalide ou expiré'], 400);
        }

        $request->validate([
            'phone' => 'required|string',
            'signature' => 'nullable|string',
            'device_fingerprint' => 'required|string'
        ]);

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
                'signed_at' => $request->signature ? now() : null
            ]
        );

        return response()->json(['success' => true, 'message' => 'Présence enregistrée pour ' . $member->name]);
    }
}
