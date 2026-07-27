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

        // La clé ne doit jamais venir du client : sans ce nettoyage, un
        // utilisateur non scopé pourrait l'injecter en query string, et un
        // faculte_admin sans etablissement_id verrait toutes les facultés.
        $request->request->remove('scoped_etablissement_id');
        $request->query->remove('scoped_etablissement_id');

        if ($user && $user->isFaculteAdmin()) {
            if (!$user->etablissement_id) {
                // Fail-closed : un admin de faculté sans faculté n'accède à rien.
                abort(403, 'Aucun établissement associé à ce compte.');
            }

            $request->merge([
                'scoped_etablissement_id' => $user->etablissement_id,
            ]);
        }

        return $next($request);
    }
}
