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
            '2fa.super_admin'    => \App\Http\Middleware\RequireTwoFactorForSuperAdmin::class,
            'cloisonnement.modeles' => \App\Http\Middleware\RestrictModelsToEtablissement::class,
        ]);

        // Le rôle avant la recherche de la ressource. Sinon la liaison de modèle
        // passe la première : un compte sans le rôle requis recevait 404 pour un
        // identifiant inconnu et 403 pour un identifiant existant, et pouvait
        // ainsi sonder ce qui existe.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\CheckRole::class,
        );

        // Le garde de cloisonnement inspecte les modeles LIES par la route : il
        // doit tourner APRES que SubstituteBindings les ait resolus, sans quoi
        // il ne verrait que des identifiants et non des modeles Eloquent.
        $middleware->appendToPriorityList(
            after: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            append: \App\Http\Middleware\RestrictModelsToEtablissement::class,
        );

        // Proxy de confiance. En production, l'API est derriere le repartiteur de
        // Render, qui termine le TLS et transmet la requete en HTTP avec
        // X-Forwarded-Proto et X-Forwarded-For. Sans proxy approuve, Laravel ignore
        // ces en-tetes : $request->secure() est toujours faux, et ForceHttps
        // redirige chaque requete vers elle-meme (boucle de 301) ; $request->ip()
        // vaut l'adresse du repartiteur, si bien que toutes les limites de debit
        // par IP etaient partagees par l'ensemble des utilisateurs. Le
        // TrustProxies de app/Http/Middleware n'etait reference que par
        // app/Http/Kernel.php, que Laravel 12 ne charge plus.
        //
        // « * » n'approuve que le pair direct (REMOTE_ADDR) : l'IP client retenue
        // est la derniere ajoutee a X-Forwarded-For par ce pair, pas une valeur
        // qu'un client aurait placee plus a gauche dans l'en-tete.
        $middleware->trustProxies(at: '*');

        // ForceHttps etait ecrit mais n'etait enregistre NULLE PART : une requete
        // en clair etait servie telle quelle. Le seul dispositif actif etait
        // URL::forceScheme() dans AppServiceProvider, qui ne concerne que les URL
        // GENEREES par l'application, jamais les requetes entrantes. Le cahier des
        // charges annoncait pourtant une « redirection forcee par middleware ».
        //
        // EnsureFrontendRequestsAreStateful (mode « SPA stateful », cookies
        // httpOnly Sanctum) a ete retire : ni le SPA React ni l'application
        // mobile n'appellent /sanctum/csrf-cookie ni n'envoient de requete avec
        // credentials — les deux s'authentifient exclusivement par jeton
        // Bearer. Ce middleware n'avait donc d'autre effet que d'activer une
        // session pour toute origine listee dans SANCTUM_STATEFUL_DOMAINS
        // (« localhost » par defaut, faute de valeur explicite) : un appelant
        // qui se declarait Origin: http://localhost obtenait un cookie de
        // session simplement en s'authentifiant, y compris pour un compte
        // protege par la double authentification — voir la correction de
        // AuthenticatedSessionController::store().
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceHttps::class,
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
