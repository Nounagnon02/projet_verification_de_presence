<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion des « sessions » actives de l'utilisateur.
 *
 * L'authentification se faisant par tokens Sanctum (et non par sessions web),
 * une « session » correspond ici à un token d'accès personnel — donc à un
 * appareil/navigateur connecté. Révoquer une session = supprimer le token, ce
 * qui déconnecte réellement l'appareil concerné (l'ancienne implémentation
 * agissait sur la table `sessions`, sans lien avec l'auth par token).
 */
class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currentId = optional($request->user()->currentAccessToken())->id;

        $sessions = $request->user()->tokens()->latest()->get()->map(fn ($t) => [
            'id'          => $t->id,
            'name'        => $t->name,
            'is_current'  => $t->id === $currentId,
            'last_active' => $t->last_used_at ? $t->last_used_at->diffForHumans() : 'jamais utilisé',
            'created_at'  => $t->created_at?->format('Y-m-d H:i'),
        ]);

        return $this->successResponse($sessions);
    }

    public function destroyOthers(Request $request): JsonResponse
    {
        $currentId = optional($request->user()->currentAccessToken())->id;

        // Révoque tous les tokens SAUF celui de la requête courante : les autres
        // appareils sont réellement déconnectés (leur token devient invalide).
        $request->user()->tokens()
            ->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
            ->delete();

        return $this->successResponse(null, 'Les autres appareils ont été déconnectés.');
    }
}
