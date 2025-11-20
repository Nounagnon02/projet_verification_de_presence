<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $locale = Session::get('locale', config('app.locale'));
            
            if (in_array($locale, ['fr', 'en', 'es'])) {
                App::setLocale($locale);
            }
        } catch (\Exception $e) {
            // Fallback si session non disponible
            App::setLocale(config('app.locale'));
        }
        
        return $next($request);
    }
}