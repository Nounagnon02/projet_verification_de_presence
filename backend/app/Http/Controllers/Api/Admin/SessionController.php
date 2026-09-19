<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SessionResource;
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

        $sessions = $request->user()->tokens()->latest()->get()
            ->each(fn ($t) => $t->setAttribute('is_current', $t->id === $currentId));

        return $this->successResponse(SessionResource::collection($sessions));
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
