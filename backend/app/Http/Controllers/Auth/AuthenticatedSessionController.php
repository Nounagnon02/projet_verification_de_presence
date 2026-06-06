<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;


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

            // Régénérer la session uniquement si disponible (API Symfony)
            try {
                $request->session()->regenerate();
            } catch (\RuntimeException $e) {
                // Session non disponible (routes API sans session middleware)
            }

            // Générer un token Sanctum pour les clients API (curl / frontend)
            $token = null;
            if (method_exists($request->user(), 'createToken')) {
                $token = $request->user()->createToken('api-token')->plainTextToken;
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Connecté avec succès.',
                    'data'    => [
                        'user'     => $request->user(),
                        'token'    => $token,
                    ],
                ]);
            }

            return redirect()->intended(route('dashboard', absolute: false));
        } catch (\Exception $e) {
            Log::error('Erreur de connexion: ' . $e->getMessage());

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Erreur de connexion.',
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
        // Révoquer le token Sanctum si présent
        if ($request->user() && method_exists($request->user(), 'currentAccessToken')) {
            $token = $request->user()->currentAccessToken();
            if ($token && method_exists($token, 'delete')) {
                $token->delete();
            }
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
