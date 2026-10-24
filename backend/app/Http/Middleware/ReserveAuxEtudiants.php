<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Etudiant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve une route à un étudiant authentifié.
 *
 * Le contrôle de capacité (« ability:etudiant ») ne suffit pas : une requête
 * authentifiée par session, et non par jeton, reçoit de Sanctum un jeton
 * transitoire qui satisfait n'importe quelle capacité. Un compte du personnel
 * connecté par cookie franchissait donc la capacité, puis la route recevait un
 * utilisateur qui n'est pas un étudiant.
 */
class ReserveAuxEtudiants
{
    public function handle(Request $request, Closure $next): Response
    {
        // Le garde Sanctum peut renvoyer un Etudiant, ce que le typage du garde
        // par défaut (User) ne sait pas exprimer.
        /** @var \Illuminate\Contracts\Auth\Authenticatable|null $utilisateur */
        $utilisateur = $request->user();

        if (! $utilisateur instanceof Etudiant) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux étudiants.',
            ], 403);
        }

        return $next($request);
    }
}
