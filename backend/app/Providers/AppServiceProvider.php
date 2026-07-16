<?php

namespace App\Providers;

use App\Models\Ec;
use App\Models\Ue;
use App\Observers\EcObserver;
use App\Observers\UeObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Enregistre les services de l'application.
     */
    public function register(): void
    {
        //
    }

    /**
     * Démarre les services de l'application.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // Enregistrement du namespace mail pour les templates d'email
        // Les vues publiées sont dans resources/views/vendor/mail/
        View::addNamespace('mail', resource_path('views/vendor/mail/html'));


        // Rate Limiting pour le scan de présence (CDC 9.2.4)
        // Limite : 3 requêtes par minute par device ou par IP
        RateLimiter::for('scan-presence', function (Request $request) {
            $key = $request->input('device_fingerprint')
                ?: $request->ip()
                ?? 'unknown';

            return Limit::perMinute(3)
                ->by('scan:' . $key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Trop de tentatives. Veuillez patienter avant de rescanner.',
                    ], 429, $headers);
                });
        });

        // Observateurs pour l'auto-inscription aux cours (CDC 7.2.3)
        // Quand une UE ou un EC est créé/modifié, les inscriptions des étudiants
        // de la filière et année correspondante sont mises à jour automatiquement.
        Ue::observe(UeObserver::class);
        Ec::observe(EcObserver::class);

        // Rate Limiting pour les routes API admin (CDC 9)
        // Limite : 60 requêtes par minute par utilisateur
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip() ?? 'unknown';
            return Limit::perMinute(60)
                ->by('api:' . $key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Trop de requêtes. Veuillez ralentir.',
                    ], 429, $headers);
                });
        });

        // Rate Limiting pour le login admin (CDC 9.1)
        // Limite : 5 tentatives par minute par IP
        RateLimiter::for('login', function (Request $request) {
            $key = $request->input('email')
                ?: $request->ip()
                ?? 'unknown';

            return Limit::perMinute(5)
                ->by('login:' . $key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Trop de tentatives de connexion. Réessayez dans 1 minute.',
                    ], 429, $headers);
                });
        });
    }
}
