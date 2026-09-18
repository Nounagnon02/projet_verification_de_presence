<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impose la double authentification au super administrateur.
 *
 * Le rôle le plus privilégié du système n'imposait jamais la 2FA, alors même
 * qu'elle existe et que le groupe super-admin est, par ailleurs, celui qui
 * échappait aussi au rate limiting et à password.changed (voir routes/api.php).
 * Un mot de passe compromis y donnait donc un accès total, sans second facteur
 * pour l'arrêter.
 *
 * Les routes de configuration de la 2FA elle-même (POST /admin/profile/2fa/*)
 * vivent dans le groupe « admin », pas « super-admin » : ce middleware ne s'y
 * applique pas, et un super admin non encore équipé peut donc l'activer.
 */
class RequireTwoFactorForSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->two_factor_confirmed_at === null) {
            return response()->json([
                'success' => false,
                'code'    => 'two_factor_setup_required',
                'message' => "L'authentification à deux facteurs est obligatoire pour ce rôle. "
                    . 'Activez-la depuis votre profil avant de continuer.',
            ], 403);
        }

        return $next($request);
    }
}
