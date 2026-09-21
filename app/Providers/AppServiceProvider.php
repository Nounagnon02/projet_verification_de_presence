<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // Vite 5+ écrit le manifest sous .vite/manifest.json par défaut (build/manifest.json
        // n'existe plus) : indiquer explicitement à Laravel où le trouver.
        Vite::useManifestFilename('.vite/manifest.json');
    }
}
