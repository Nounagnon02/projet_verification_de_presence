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
        $qrCode = QrCode::create([
            'event_date' => $request->date ?? today(),
            'event_name' => $request->event_name,
            'created_by' => Auth::id(),
            'expires_at' => now()->addHours(1)
        ]);

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

        $members = member::all();
        return view('qr.scan', compact('qrCode', 'members'));
    }

    public function markPresence(Request $request, $code)
    {
        $qrCode = QrCode::where('code', $code)->first();

        if (!$qrCode || !$qrCode->isValid()) {
            return response()->json(['error' => 'QR Code invalide'], 400);
        }

        $request->validate([
            'member_id' => 'required|exists:members,id',
            'signature' => 'nullable|string'
        ]);

        $presence = Presence::updateOrCreate(
            [
                'member_id' => $request->member_id,
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

        return response()->json(['success' => true, 'message' => 'Présence enregistrée']);
    }
}
