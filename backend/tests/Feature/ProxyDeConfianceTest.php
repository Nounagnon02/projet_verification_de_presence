<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Proxy de confiance.
 *
 * En production, le répartiteur de Render termine le TLS et relaie la requête
 * en HTTP avec X-Forwarded-Proto et X-Forwarded-For. Sans proxy approuvé,
 * Laravel ignorait ces en-têtes : ForceHttps voyait une requête en clair et la
 * redirigeait vers elle-même, indéfiniment, et $request->ip() valait l'adresse
 * du répartiteur, de sorte que toutes les limites de débit par IP étaient
 * partagées par tous les utilisateurs.
 *
 * Les tests tournent en « testing », où ForceHttps ne fait rien : c'est
 * pourquoi rien ne l'avait signalé. On simule donc la production.
 */
class ProxyDeConfianceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Route sonde : même groupe « api » que les vraies routes, donc même
        // passage par ForceHttps.
        Route::middleware('api')->get('/api/_sonde-proxy', fn (Request $request) => response()->json([
            'ip' => $request->ip(),
            'secure' => $request->secure(),
        ]));

        $this->app['env'] = 'production';
    }

    public function test_une_requete_relayee_en_https_par_le_proxy_n_est_pas_redirigee(): void
    {
        $this->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->getJson('/api/_sonde-proxy')
            ->assertOk()
            ->assertJsonPath('secure', true);
    }

    public function test_une_requete_en_clair_sans_proxy_reste_redirigee_vers_https(): void
    {
        $reponse = $this->getJson('/api/_sonde-proxy');

        $reponse->assertStatus(301);
        $this->assertStringStartsWith('https://', $reponse->headers->get('Location'));
    }

    public function test_l_ip_retenue_est_celle_du_client_transmise_par_le_proxy(): void
    {
        $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '203.0.113.7',
        ])
            ->getJson('/api/_sonde-proxy')
            ->assertOk()
            ->assertJsonPath('ip', '203.0.113.7');
    }

    public function test_une_ip_placee_par_le_client_dans_x_forwarded_for_est_ignoree(): void
    {
        // Le client envoie « 1.2.3.4 » ; le proxy ajoute l'adresse réelle à
        // droite. Seul le pair direct est approuvé : c'est la valeur la plus à
        // droite qui compte, pas celle que le client a choisie.
        $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '1.2.3.4, 203.0.113.7',
        ])
            ->getJson('/api/_sonde-proxy')
            ->assertOk()
            ->assertJsonPath('ip', '203.0.113.7');
    }
}
