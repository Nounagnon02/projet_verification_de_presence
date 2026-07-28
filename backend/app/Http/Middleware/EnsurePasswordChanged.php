<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force le changement du mot de passe temporaire.
 *
 * Les admins de faculté sont créés avec un mot de passe aléatoire et
 * must_change_password = true. Tant que ce flag est vrai, on bloque l'accès
 * aux routes protégées — sauf la route de changement de mot de passe
 * elle-même — pour que le mot de passe temporaire ne reste pas valide
 * indéfiniment.
 *
 * Le front détecte `must_change_password: true` dans la réponse 403 et
 * redirige vers l'écran de changement de mot de passe.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->must_change_password ?? false)) {
            // La route qui permet justement de régulariser reste accessible.
            if ($request->is('api/admin/profile/password')) {
                return $next($request);
            }

            return response()->json([
                'success'              => false,
                'message'              => 'Vous devez définir un nouveau mot de passe avant de continuer.',
                'must_change_password' => true,
            ], 403);
        }

        return $next($request);
    }
}
