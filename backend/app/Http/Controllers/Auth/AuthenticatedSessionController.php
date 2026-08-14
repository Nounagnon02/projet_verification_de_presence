<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class AuthenticatedSessionController extends Controller
{
    /**
     * Vérifie le second facteur : soit un code TOTP à 6 chiffres, soit l'un
     * des codes de récupération à usage unique (qui est alors consommé).
     */
    private function verifyTwoFactor($user, string $code): bool
    {
        // Code TOTP (6 chiffres).
        if (preg_match('/^\d{6}$/', $code) && $user->two_factor_secret) {
            if ((new Google2FA())->verifyKey($user->two_factor_secret, $code)) {
                return true;
            }
        }

        // Code de récupération à usage unique.
        $recovery = json_decode($user->two_factor_recovery_codes ?? '[]', true) ?: [];
        $index = array_search($code, $recovery, true);
        if ($index !== false) {
            unset($recovery[$index]);
            $user->two_factor_recovery_codes = json_encode(array_values($recovery));
            $user->save();
            return true;
        }

        return false;
    }

    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $request->authenticate();

            // Régénérer la session uniquement si disponible
            try {
                $request->session()->regenerate();
            } catch (\RuntimeException $e) {
                // Session non disponible (routes API sans session middleware)
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                $user = $request->user();

                // Portail 2FA : si l'utilisateur a activé la double authentification,
                // le mot de passe seul ne suffit pas. On ne délivre le token qu'après
                // vérification du code TOTP (ou d'un code de récupération).
                if ($user->two_factor_confirmed_at !== null) {
                    $code = trim((string) $request->input('code', ''));

                    if ($code === '') {
                        // Étape 1 réussie (mot de passe correct), étape 2 requise.
                        // Aucun token n'est émis tant que le code n'est pas fourni.
                        return response()->json([
                            'success'             => false,
                            'two_factor_required' => true,
                            'message'             => 'Code d\'authentification à deux facteurs requis.',
                        ], 200);
                    }

                    if (! $this->verifyTwoFactor($user, $code)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Code d\'authentification à deux facteurs invalide.',
                        ], 422);
                    }
                }

                // Créer un token Sanctum API pour les clients mobiles / SPA JSON
                $token = $user->createToken('api-token')->plainTextToken;

                return response()->json([
                    'success' => true,
                    'message' => 'Connecté avec succès.',
                    'data'    => [
                        'user'  => $user,
                        'token' => $token,
                    ],
                ]);
            }

            return redirect()->intended(route('dashboard', absolute: false));
        } catch (\Exception $e) {
            Log::error('Erreur de connexion', [
                'email'      => $request->input('email', 'unknown'),
                'error_type' => get_class($e),
            ]);

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Identifiants invalides ou compte bloqué. Veuillez réessayer.',
                ], 422);
            }

            return back()->withErrors([
                'email' => 'Une erreur est survenue lors de la connexion. Veuillez réessayer.',
            ])->withInput($request->except('password'));
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        // Révoquer TOUS les tokens Sanctum de l'utilisateur
        if ($request->user()) {
            $request->user()->tokens()->delete();
        }

        try {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } catch (\RuntimeException $e) {
            // Session non disponible
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Déconnecté avec succès.',
            ]);
        }

        return redirect('/');
    }
}
