<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * OWASP A05 (mauvaise configuration) — ce que l'API montre sans authentification.
 *
 * Deux défauts réels avaient été relevés à l'audit du 2026-09-19 :
 *
 *  1. /api/docs chargeait Swagger UI depuis unpkg.com sans contrôle d'intégrité
 *     (une version épinglée n'empêche pas un CDN compromis de servir autre chose),
 *     alors que la CSP de production interdisait déjà ce chargement : la page s'y
 *     affichait vide tout en restant une surface publique.
 *  2. /api/health annonçait l'état de la base, la version et l'heure du serveur,
 *     et répondait « healthy » en 200 même base coupée — un moniteur ne pouvait
 *     donc jamais s'en servir pour alerter.
 *
 * Ce fichier verrouille les deux corrections.
 */
class SurfacePubliqueTest extends TestCase
{
    private function enProduction(): void
    {
        // ForceHttps est actif en production : on simule le répartiteur de Render.
        $this->app['env'] = 'production';
        $this->withHeaders(['X-Forwarded-Proto' => 'https']);
    }

    // ---------------------------------------------------------------- /api/health

    public function test_la_sonde_ne_repond_que_oui(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['success' => true, 'status' => 'healthy']);
    }

    public function test_la_sonde_reste_muette_en_production(): void
    {
        $this->enProduction();

        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['success' => true, 'status' => 'healthy']);
    }

    public function test_la_sonde_repond_503_sans_detail_quand_la_base_est_injoignable(): void
    {
        $normale = config('database.default');

        // Rien n'écoute sur le port 1 : refus immédiat, sans attendre de délai.
        config([
            'database.connections.panne' => array_merge(config("database.connections.{$normale}"), [
                'url' => null,
                'host' => '127.0.0.1',
                'port' => 1,
            ]),
            'database.default' => 'panne',
        ]);

        try {
            $reponse = $this->getJson('/api/health');
        } finally {
            // Avant la fin du test : la transaction de TestCase se referme sur
            // la connexion par défaut, qui doit redevenir la vraie.
            config(['database.default' => $normale]);
            DB::purge('panne');
        }

        $reponse->assertStatus(503)
            ->assertExactJson(['success' => false, 'status' => 'unavailable']);

        $corps = $reponse->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $corps);
        $this->assertStringNotContainsString('127.0.0.1', $corps);
    }

    // ------------------------------------------------------------------ /api/docs

    public function test_la_page_swagger_est_servie_hors_production(): void
    {
        $this->get('/api/docs')
            ->assertOk()
            ->assertSee('swagger-ui', false);
    }

    public function test_chaque_ressource_tierce_de_la_page_swagger_porte_son_empreinte(): void
    {
        $html = $this->get('/api/docs')->assertOk()->getContent();

        preg_match_all(
            '#<(?:script|link)\b[^>]*\b(?:src|href)="https?://[^"]+"[^>]*>#s',
            $html,
            $balises
        );

        $this->assertNotEmpty($balises[0], 'La page ne charge plus rien depuis un tiers : ce test est à revoir.');

        foreach ($balises[0] as $balise) {
            $this->assertMatchesRegularExpression(
                '#\bintegrity="sha384-[A-Za-z0-9+/]{64}"#',
                $balise,
                "Ressource tierce sans empreinte SRI : {$balise}"
            );
            $this->assertStringContainsString('crossorigin="anonymous"', $balise);
        }
    }

    public function test_swagger_ne_contacte_pas_le_validateur_public(): void
    {
        // Sans « validatorUrl: null », Swagger UI envoie l'adresse de la
        // spécification à validator.swagger.io pour afficher un badge.
        $this->get('/api/docs')->assertSee('validatorUrl: null', false);
    }

    public function test_la_derogation_de_csp_ne_vaut_que_pour_la_page_swagger(): void
    {
        $swagger = $this->get('/api/docs')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' https://unpkg.com", $swagger);
        $this->assertStringContainsString("connect-src 'self'", $swagger);

        foreach (['/api/health', '/api/docs/json', '/api/docs/yaml'] as $uri) {
            $this->assertStringNotContainsString(
                'unpkg.com',
                $this->get($uri)->headers->get('Content-Security-Policy'),
                "{$uri} ne doit pas hériter de la dérogation accordée à la page Swagger."
            );
        }
    }

    public function test_la_page_swagger_est_absente_en_production(): void
    {
        $this->enProduction();

        $reponse = $this->getJson('/api/docs');

        $reponse->assertNotFound();
        $this->assertStringNotContainsString('swagger', strtolower($reponse->getContent()));
        $this->assertStringNotContainsString(
            'unpkg.com',
            $reponse->headers->get('Content-Security-Policy'),
            'Le 404 de production n’a aucune raison d’autoriser un CDN.'
        );
    }

    public function test_la_specification_reste_servie_en_production(): void
    {
        // Elle pilote la passe OWASP ZAP et le contrat avec les clients : seule
        // l'interface tierce est retirée.
        $this->enProduction();

        $this->getJson('/api/docs/json')->assertOk()->assertJsonStructure(['openapi', 'paths']);
        $this->get('/api/docs/yaml')->assertOk();
    }

    public function test_la_specification_json_ne_depend_pas_d_un_paquet_de_developpement(): void
    {
        // Les tests tournent avec les dépendances de développement : ils ne
        // peuvent pas voir qu'un paquet manque à l'image de production
        // (composer install --no-dev). ApiDocumentationController::json() lit le
        // YAML avec symfony/yaml, que seul laravel/sail (développement) tirait :
        // /api/docs/json répondait 500 en production, tests au vert.
        $installes = collect(json_decode(file_get_contents(base_path('composer.lock')), true)['packages'])
            ->pluck('name');
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        $this->assertArrayHasKey('symfony/yaml', $composer['require']);
        $this->assertTrue(
            $installes->contains('symfony/yaml'),
            'symfony/yaml doit figurer dans les paquets de production de composer.lock.'
        );
    }
}
