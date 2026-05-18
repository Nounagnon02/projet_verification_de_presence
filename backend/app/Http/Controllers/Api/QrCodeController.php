<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class QrCodeController extends Controller
{
    /**
     * Génère ou rafraîchit un token QR Code pour un événement.
     * CDC 7.4.2: Régénération toutes les 60 secondes.
     */
    public function generate(Request $request, $evenementId)
    {
        $evenement = Evenement::findOrFail($evenementId);

        // Inactiver les anciens tokens
        QrCode::where('evenement_id', $evenementId)->update(['actif' => false]);

        // Créer un nouveau token (UUID v4)
        $token = (string) Str::uuid();
        $expireAt = Carbon::now()->addSeconds(60);

        $qrCode = QrCode::create([
            'evenement_id' => $evenementId,
            'token' => $token,
            'expire_at' => $expireAt,
            'actif' => true,
        ]);

        return response()->json([
            'token' => $token,
            'expire_at' => $expireAt->toIso8601String(),
            'expires_in' => 60,
        ]);
    }
}
