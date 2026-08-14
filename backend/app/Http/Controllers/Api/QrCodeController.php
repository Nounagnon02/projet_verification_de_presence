<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Models\QrCode;
use App\Services\QrCodeImageService;
use App\Traits\ScopedByEtablissement;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QrCodeController extends Controller
{
    use ScopedByEtablissement;

    /**
     * Génère ou rafraîchit un token QR Code pour un événement.
     * CDC 7.4.2 : régénération selon presence.qr.ttl_secondes.
     *
     * GET /api/admin/qrcode/{evenementId}/generate
     */
    public function generate(Request $request, int $evenementId, QrCodeImageService $images): JsonResponse
    {
        $evenement = Evenement::findOrFail($evenementId);

        // Empêche un admin de faculté de générer un QR pour l'événement d'une
        // autre faculté (le QR déverrouille la prise de présence du cours).
        $this->authorizeEtablissement($evenement, $request, 'filiere');

        QrCode::where('evenement_id', $evenementId)->update(['actif' => false]);

        $maintenant = Carbon::now();
        $token      = (string) Str::uuid();

        // L'expiration est plafonnée à la fermeture de la fenêtre de scan : un
        // code généré à la main dans les dernières secondes du cours ne doit pas
        // rester valide après que la prise de présence est close.
        $expireAt = $evenement->expirationTokenDepuis($maintenant);

        QrCode::create([
            'evenement_id' => $evenementId,
            'token'        => $token,
            'expire_at'    => $expireAt,
            'actif'        => true,
        ]);

        return $this->successResponse([
            'token'      => $token,
            'expire_at'  => $expireAt->toIso8601String(),
            'expires_in' => max(0, $maintenant->diffInSeconds($expireAt, false)),
            // Image générée sur le serveur. Le frontend la rendait auparavant en
            // appelant un service tiers, ce qui faisait sortir le token du
            // système pour un simple encodage graphique.
            'svg'        => $images->svg($token),
            'url'        => $images->urlValidation($token),
        ]);
    }
}
