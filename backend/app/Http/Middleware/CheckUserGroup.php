<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
class CheckUserGroup
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Vérifier que l'utilisateur n'essaie pas d'accéder à des données d'un autre groupe
        if ($request->has('group') && $request->group !== $user->group) {
            abort(403, 'Accès non autorisé à ce groupe.');
        }

        return $next($request);
    }
}
