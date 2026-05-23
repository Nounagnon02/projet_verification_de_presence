<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Models\QrCode;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QrCodeController extends Controller
{
    /**
     * Génère ou rafraîchit un token QR Code pour un événement.
     * CDC 7.4.2 : Régénération toutes les 60 secondes.
     *
     * GET /api/admin/qrcode/{evenementId}/generate
     */
    public function generate(Request $request, int $evenementId): JsonResponse
    {
        $evenement = Evenement::findOrFail($evenementId);

        QrCode::where('evenement_id', $evenementId)->update(['actif' => false]);

        $token   = (string) Str::uuid();
        $expireAt = Carbon::now()->addSeconds(60);

        QrCode::create([
            'evenement_id' => $evenementId,
            'token'        => $token,
            'expire_at'    => $expireAt,
            'actif'        => true,
        ]);

        return $this->successResponse([
            'token'     => $token,
            'expire_at' => $expireAt->toIso8601String(),
            'expires_in' => 60,
        ]);
    }
}
