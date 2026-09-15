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
            'locale'             => \App\Http\Middleware\SetLocale::class,
            'role'               => \App\Http\Middleware\CheckRole::class,
            'scoped.etablissement' => \App\Http\Middleware\ScopeByEtablissement::class,
            'security.headers'   => \App\Http\Middleware\SecurityHeaders::class,
            'password.changed'   => \App\Http\Middleware\EnsurePasswordChanged::class,
            // Cloisonnement des jetons : auth:sanctum authentifie aussi bien un
            // administrateur qu'un étudiant. Les capacités permettent de refuser
            // un jeton d'étudiant sur une route d'administration, et l'inverse.
            'ability'            => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'abilities'          => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        ]);

        // Le rôle avant la recherche de la ressource. Sinon la liaison de modèle
        // passe la première : un compte sans le rôle requis recevait 404 pour un
        // identifiant inconnu et 403 pour un identifiant existant, et pouvait
        // ainsi sonder ce qui existe.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\CheckRole::class,
        );

        // SPA stateful auth (cookies httpOnly Sanctum) + Security headers
        //
        // ForceHttps etait ecrit mais n'etait enregistre NULLE PART : une requete
        // en clair etait servie telle quelle. Le seul dispositif actif etait
        // URL::forceScheme() dans AppServiceProvider, qui ne concerne que les URL
        // GENEREES par l'application, jamais les requetes entrantes. Le cahier des
        // charges annoncait pourtant une « redirection forcee par middleware ».
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceHttps::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Forcer JSON pour toutes les routes API
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // 404 — ressource absente ou URL inconnue.
        //
        // Laravel convertit ModelNotFoundException en NotFoundHttpException dans
        // prepareException(), avant d'atteindre les callbacks : un handler dedie a
        // ModelNotFoundException n'est donc jamais appele. Sans regarder
        // l'exception d'origine, une ressource absente et une URL erronee
        // renvoyaient le meme « Route non trouvée. », et un client ne pouvait pas
        // distinguer une faute de frappe dans l'URL d'un identifiant inexistant.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            $ressourceAbsente = $e->getPrevious()
                instanceof \Illuminate\Database\Eloquent\ModelNotFoundException;

            return response()->json([
                'success' => false,
                'message' => $ressourceAbsente ? 'Ressource non trouvée.' : 'Route non trouvée.',
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
