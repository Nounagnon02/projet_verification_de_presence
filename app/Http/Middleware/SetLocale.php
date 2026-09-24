<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Nom du cookie qui retient le choix de langue d'un visiteur non connecté.
     */
    public const COOKIE = 'locale';

    /**
     * Durée de vie du cookie, en minutes (un an).
     */
    public const COOKIE_MINUTES = 525600;

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        App::setLocale($locale);

        // Le fournisseur de services de Carbon écoute LocaleUpdated et suit
        // App::setLocale, mais il ne connaît pas « fon ». On impose donc la
        // locale de dates déclarée dans config/locales.php : sans ça, dans un
        // processus qui sert plusieurs requêtes (worker, Octane), Carbon garde
        // la locale de la requête précédente.
        Carbon::setLocale(config("locales.supported.{$locale}.carbon", 'fr'));

        return $next($request);
    }

    /**
     * Détermine la langue de la requête, par ordre de priorité décroissante.
     */
    private function resolve(Request $request): string
    {
        $supported = array_keys(config('locales.supported', []));

        // 1. Préférence enregistrée sur le compte : elle suit l'utilisateur
        //    d'un appareil à l'autre.
        $user = $request->user();

        if ($user && in_array($user->locale, $supported, true)) {
            return $user->locale;
        }

        // 2. Choix fait pendant la session en cours.
        if ($request->hasSession() && in_array($request->session()->get(self::COOKIE), $supported, true)) {
            return $request->session()->get(self::COOKIE);
        }

        // 3. Choix d'un visiteur non connecté, retenu d'une visite à l'autre.
        if (in_array($request->cookie(self::COOKIE), $supported, true)) {
            return $request->cookie(self::COOKIE);
        }

        // 4. Langue du navigateur, si elle fait partie des langues gérées.
        $preferred = $request->getPreferredLanguage($supported);

        if (in_array($preferred, $supported, true)) {
            return $preferred;
        }

        // 5. Langue par défaut de l'application.
        return config('app.locale', 'fr');
    }
}
