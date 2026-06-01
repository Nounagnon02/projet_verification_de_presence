<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'locale' => \App\Http\Middleware\SetLocale::class,
        ]);

        // Note : SPA stateful auth désactivé — on utilise exclusivement
        // les tokens Bearer Sanctum pour l'authentification API.
        // $middleware->api(prepend: [
        //     \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Forcer JSON pour toutes les routes API
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // ModelNotFoundException → 404
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ressource non trouvée.',
            ], 404);
        });

        // NotFoundHttpException → 404
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Route non trouvée.',
            ], 404);
        });

        // AuthenticationException → 401
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié.',
            ], 401);
        });

        // ValidationException → 422
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation.',
                'errors'  => $e->errors(),
            ], 422);
        });
    })->create();
