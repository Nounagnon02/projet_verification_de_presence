<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render termine le TLS à son edge et transmet en HTTP interne :
        // sans ça, $request->secure() reste toujours false et ForceHttps boucle en redirection.
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            \App\Http\Middleware\ForceHttps::class,
        ]);
        $middleware->alias([
            'locale' => \App\Http\Middleware\SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
