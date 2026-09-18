<?php

namespace App\Providers;

use App\Contracts\AiProviderInterface;
use App\Models\Ec;
use App\Models\Ue;
use App\Observers\EcObserver;
use App\Observers\UeObserver;
use App\Services\AiAnalysisService;
use App\Services\Providers\GeminiProvider;
use App\Services\Providers\GroqProvider;
use App\Services\Providers\OpenRouterProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Enregistre les services de l'application.
     */
    public function register(): void
    {
        // Binding du provider IA — interchangeable via AI_PROVIDER
        $this->app->bind(AiProviderInterface::class, function ($app) {
            return match (config('ai.default')) {
                'groq'       => new GroqProvider(config('ai.providers.groq.api_key')),
                'openrouter' => new OpenRouterProvider(config('ai.providers.openrouter.api_key')),
                default      => new GeminiProvider(config('ai.providers.gemini.api_key')),
            };
        });

        // Enregistrement du service d'analyse IA
        $this->app->singleton(AiAnalysisService::class, function ($app) {
            return new AiAnalysisService($app->make(AiProviderInterface::class));
        });
    }

    /**
     * Démarre les services de l'application.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // Politique de mot de passe (CDC 9.1). ProfileController::updatePassword
        // n'exigeait qu'un « min:8 » sans complexité ; NewPasswordController,
        // PasswordController et RegisteredUserController appelaient déjà
        // Password::defaults(), mais rien ne le définissait — c'était donc,
        // en silence, le défaut du framework, lui aussi un simple min:8.
        //
        // ->uncompromised() appelle l'API haveibeenpwned.com : réservé à la
        // production, où le réseau sortant est disponible ; en test, cet
        // appel échouerait ou ralentirait chaque test de mot de passe.
        Password::defaults(function () {
            $regle = Password::min(10)->letters()->numbers();

            return $this->app->environment('production') ? $regle->uncompromised() : $regle;
        });

        // Enregistrement du namespace mail pour les templates d'email
        // Les vues publiées sont dans resources/views/vendor/mail/
        View::addNamespace('mail', resource_path('views/vendor/mail/html'));


        // Rate Limiting pour le scan de présence (CDC 9.2.4)
        // Limite : 3 requêtes par minute par étudiant authentifié ET par IP.
        //
        // Indexée sur « device_fingerprint » auparavant — une valeur fournie
        // par le client, qu'une empreinte aléatoire par requête suffisait à
        // faire sortir de toute limite. Le scan étant désormais authentifié
        // (auth:sanctum, ability:etudiant), l'identité de l'appelant est
        // garantie par le serveur ; l'IP, elle, n'est fiable que parce que le
        // répartiteur de Render est déclaré proxy de confiance
        // (bootstrap/app.php) — sans quoi elle serait, elle aussi, une entête
        // que le client écrit lui-même.
        RateLimiter::for('scan-presence', function (Request $request) {
            $reponseSaturee = function (Request $request, array $headers) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trop de tentatives. Veuillez patienter avant de rescanner.',
                ], 429, $headers);
            };

            return [
                Limit::perMinute(3)->by('scan:etudiant:' . $request->user()?->id)->response($reponseSaturee),
                Limit::perMinute(3)->by('scan:ip:' . $request->ip())->response($reponseSaturee),
            ];
        });

        // Rate Limiting pour le login étudiant (app mobile).
        // L'identifiant unique est déterministe (NOM_PRENOM_MATRICULE_FILIERE_ANNEE)
        // donc devinable : sans limite, le couple email/identifiant est
        // énumérable par force brute. Double clé : par email ET par IP.
        RateLimiter::for('student-login', function (Request $request) {
            return [
                Limit::perMinute(5)->by('student-login:email:' . (string) $request->input('email'))
                    ->response(fn(Request $r, array $h) => response()->json([
                        'success' => false,
                        'message' => 'Trop de tentatives de connexion. Réessayez dans 1 minute.',
                    ], 429, $h)),
                Limit::perMinute(20)->by('student-login:ip:' . $request->ip())
                    ->response(fn(Request $r, array $h) => response()->json([
                        'success' => false,
                        'message' => 'Trop de tentatives depuis cette adresse. Réessayez plus tard.',
                    ], 429, $h)),
            ];
        });

        // Personnalisation de l'URL de réinitialisation du mot de passe
        // Le lien dans l'email pointe vers le frontend (SPA React)
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url') . '/reset-password?token=' . $token . '&email=' . $notifiable->getEmailForPasswordReset();
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
        // Limite : 5 tentatives par minute par email ET par IP — double clé,
        // comme « student-login ». Indexée sur le seul email auparavant : en
        // faisant varier l'email essayé, un mot de passe se testait contre des
        // milliers de comptes sans jamais être ralenti (password spraying).
        RateLimiter::for('login', function (Request $request) {
            $reponseSaturee = function (Request $request, array $headers) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trop de tentatives de connexion. Réessayez dans 1 minute.',
                ], 429, $headers);
            };

            return [
                Limit::perMinute(5)->by('login:email:' . Str::lower((string) $request->input('email')))->response($reponseSaturee),
                Limit::perMinute(20)->by('login:ip:' . $request->ip())->response($reponseSaturee),
            ];
        });

        // Rate Limiting dédié à la vérification du code TOTP (CDC 9.1).
        // ProfileController::confirm2FA()/verify2FA() n'étaient couverts que
        // par le throttle général de 60 requêtes/minute : un espace de 10^6
        // codes s'épuise largement dans cette marge.
        RateLimiter::for('totp', function (Request $request) {
            $cle = $request->user()?->id ?: $request->ip() ?? 'unknown';

            return Limit::perMinute(5)
                ->by('totp:' . $cle)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Trop de tentatives. Veuillez patienter avant de réessayer.',
                    ], 429, $headers);
                });
        });
    }
}
