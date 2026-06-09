<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['members']);
        return $this->successResponse([
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'role'      => $user->role,
            'member'    => $user->members->first() ? [
                'matricule' => $user->members->first()->matricule,
                'telephone' => $user->members->first()->telephone,
            ] : null,
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            'created_at' => $user->created_at->format('Y-m-d'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $request->user()->id,
        ]);

        $request->user()->update($validated);

        return $this->successResponse([
            'id'    => $request->user()->id,
            'name'  => $request->user()->name,
            'email' => $request->user()->email,
        ], 'Profil mis à jour.');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|current_password',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return $this->successResponse(null, 'Mot de passe mis à jour.');
    }

    /**
     * Étape 1 : Activer la 2FA — génère un secret TOTP et retourne le QR code.
     */
    public function enable2FA(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at !== null) {
            return $this->errorResponse('L\'authentification à deux facteurs est déjà activée.', 422);
        }

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey(32);

        // Stocker temporairement le secret en attendant confirmation
        $user->two_factor_secret = $secret;
        $user->save();

        $company = config('app.name', 'Attendance System');
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            $company,
            $user->email,
            $secret
        );

        // Générer le QR code en SVG inline via simple-qrcode
        $qrCodeSvg = (string) QrCode::size(250)->generate($qrCodeUrl); // Caster en string pour le JSON

        return $this->successResponse([
            'secret'          => $secret,
            'qr_code'         => $qrCodeSvg,
            'qr_code_url'     => $qrCodeUrl,
        ], 'Scannez le QR code avec votre application d\'authentification.');
    }

    /**
     * Étape 2 : Confirmer la 2FA en vérifiant un code TOTP.
     */
    public function confirm2FA(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($user->two_factor_secret === null) {
            return $this->errorResponse('Veuillez d\'abord générer un secret 2FA.', 422);
        }

        if ($user->two_factor_confirmed_at !== null) {
            return $this->errorResponse('L\'authentification à deux facteurs est déjà activée.', 422);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $validated['code']);

        if (!$valid) {
            return $this->errorResponse('Le code fourni est invalide. Veuillez réessayer.', 422);
        }

        // Générer des codes de récupération
        $recoveryCodes = collect(range(1, 8))->map(fn () => bin2hex(random_bytes(5)))->toArray();

        $user->two_factor_recovery_codes = json_encode($recoveryCodes);
        $user->two_factor_confirmed_at = now();
        $user->save();

        return $this->successResponse([
            'recovery_codes' => $recoveryCodes,
        ], 'Authentification à deux facteurs activée avec succès. Conservez vos codes de récupération en lieu sûr.');
    }

    /**
     * Désactiver la 2FA.
     */
    public function disable2FA(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|current_password',
        ]);

        $user = $request->user();

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return $this->successResponse(null, 'Authentification à deux facteurs désactivée.');
    }

    /**
     * Vérifier un code 2FA (utile pour les tests ou validation en cours de session).
     */
    public function verify2FA(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($user->two_factor_confirmed_at === null) {
            return $this->errorResponse('L\'authentification à deux facteurs n\'est pas activée.', 422);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $validated['code']);

        if (!$valid) {
            return $this->errorResponse('Code invalide.', 422);
        }

        return $this->successResponse(null, 'Code valide.');
    }
}
