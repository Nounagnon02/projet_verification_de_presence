<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
    }
}
