<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Vérifie que l'utilisateur authentifié possède le rôle requis.
     *
     * Usage: Route::middleware('role:super_admin')
     *        Route::middleware('role:faculte_admin')
     *        Route::middleware('role:super_admin,faculte_admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié.',
            ], 401);
        }

        foreach ($roles as $role) {
            if ($user->role === $role) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé. Rôle requis : ' . implode(', ', $roles),
        ], 403);
    }
}
