<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Message unique, quel que soit le statut réel de l'envoi (compte
     * inconnu, lien envoyé, débit dépassé). « We can't find a user with that
     * email address » distinguait un email inconnu d'un email existant :
     * l'endpoint devenait un oracle permettant d'énumérer les comptes.
     */
    private const MESSAGE = "Si un compte existe avec cette adresse, un lien de réinitialisation vient d'être envoyé.";

    /**
     * Handle an incoming password reset link request.
     * Support à la fois les réponses JSON (API) et les vues Blade.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // La valeur de retour ($status) n'influence plus la réponse : envoyer
        // ou non le lien reste conditionné à l'existence du compte (c'est
        // Password::sendResetLink() qui en décide), mais l'appelant ne doit
        // jamais pouvoir distinguer les deux cas.
        Password::sendResetLink($request->only('email'));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => self::MESSAGE,
            ]);
        }

        return back()->with('status', self::MESSAGE);
    }
}
