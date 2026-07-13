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

class AuthenticatedSessionController extends Controller
{
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
