<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Models\member;
use App\Models\Presence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrGenerator;

class QrCodeController extends Controller
{
    public function generate(Request $request)
    {
        // Utiliser le code basé sur le temps
        $timeBasedCode = QrCode::generateTimeBasedCode();
        
        $qrCode = QrCode::updateOrCreate(
            [
                'event_date' => $request->date ?? today(),
                'created_by' => Auth::id()
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
        $currentCode = QrCode::generateTimeBasedCode();
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
            return response()->json(['error' => 'QR Code invalide'], 400);
        }

        $request->validate([
            'phone' => 'required|string',
            'signature' => 'nullable|string'
        ]);

        // Trouver le membre par son numéro de téléphone
        $member = member::where('phone', $request->phone)->first();
        if (!$member) {
            return response()->json(['error' => 'Aucun membre trouvé avec ce numéro de téléphone'], 400);
        }

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
