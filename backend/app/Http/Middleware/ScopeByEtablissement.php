<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScopeByEtablissement
{
    /**
     * Injecte automatiquement le filtre etablissement_id dans les requêtes
     * pour les admins de faculté. Le super admin n'est pas filtré.
     *
     * Les contrôleurs récupèrent le filtre via :
     *   $request->get('scoped_etablissement_id')
     * ou utilisent le scope forEtablissement() sur les modèles.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isFaculteAdmin() && $user->etablissement_id) {
            $request->merge([
                'scoped_etablissement_id' => $user->etablissement_id,
            ]);
        }

        return $next($request);
    }
}
