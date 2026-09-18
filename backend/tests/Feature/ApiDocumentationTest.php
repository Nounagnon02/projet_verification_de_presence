<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * BE-DOC-01 du plan de tests.
 *
 * Pourquoi ce fichier existe : GET /api/docs/json repondait 500 depuis un temps
 * indetermine — trois erreurs de syntaxe dans docs/openapi.yaml, dont deux cles
 * de chemin dupliquees. Le parseur YAML s'arrete a la premiere erreur, si bien
 * que chaque correction en revelait une autre.
 *
 * L'enjeu depasse la documentation. La passe OWASP ZAP est pilotee par cette
 * specification : une route absente n'est pas testee, et le rapport ressort vert
 * pour une mauvaise raison. Six chemins y decrivaient d'ailleurs des endpoints
 * inexistants, dont toute la messagerie supprimee du produit.
 */
class ApiDocumentationTest extends TestCase
{
    private const CHEMIN_SPEC = 'docs/openapi.yaml';

    /**
     * Operations reellement servies, methode et chemin normalises.
     *
     * Les noms de parametres sont neutralises : {student} et {id} designent la
     * meme chose selon qu'on lit la route ou la specification.
     */
    private function operationsReelles(): array
    {
        $operations = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (!str_starts_with($uri, 'api/')) {
                continue;
            }

            $chemin = '/' . preg_replace('#^api/#', '', $uri);
            $norme  = preg_replace('/\{[^}]+\}/', '{P}', $chemin);

            foreach ($route->methods() as $methode) {
                if (in_array($methode, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }
                $operations[strtolower($methode) . ' ' . $norme] = $uri;
            }
        }

        return $operations;
    }

    private function operationsDocumentees(array $spec): array
    {
        $operations = [];

        foreach ($spec['paths'] as $chemin => $definition) {
            $norme = preg_replace('/\{[^}]+\}/', '{P}', $chemin);

            foreach (array_keys($definition) as $methode) {
                if (!in_array($methode, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }
                $operations[$methode . ' ' . $norme] = $chemin;
            }
        }

        return $operations;
    }

    public function test_la_specification_openapi_est_analysable(): void
    {
        $chemin = base_path(self::CHEMIN_SPEC);
        $this->assertFileExists($chemin);

        try {
            $spec = Yaml::parse(file_get_contents($chemin));
        } catch (\Throwable $e) {
            $this->fail(
                "docs/openapi.yaml n'est pas analysable : {$e->getMessage()}\n"
                . "Conséquence : /api/docs/json répond 500 et la passe ZAP n'a plus de cible."
            );
        }

        $this->assertIsArray($spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertNotEmpty($spec['paths']);
    }

    public function test_l_endpoint_de_documentation_repond(): void
    {
        $this->getJson('/api/docs/json')
            ->assertStatus(200)
            ->assertJsonStructure(['paths']);
    }

    public function test_aucun_chemin_documente_n_est_inexistant(): void
    {
        $spec = Yaml::parse(file_get_contents(base_path(self::CHEMIN_SPEC)));

        $fantomes = array_diff_key(
            $this->operationsDocumentees($spec),
            $this->operationsReelles(),
        );

        $this->assertSame(
            [],
            array_keys($fantomes),
            "Ces opérations sont documentées mais n'existent pas. ZAP les teste, "
            . "obtient un 404 et n'en tire aucune conclusion utile."
        );
    }

    /**
     * Les routes non documentees sont tolerees, mais leur NOMBRE est verrouille :
     * il ne doit pas croitre. Sans ce plafond, l'ecart se creuse silencieusement
     * et la couverture de la passe de securite se degrade sans que rien ne le dise.
     */
    public function test_le_nombre_de_routes_non_documentees_ne_croit_pas(): void
    {
        $spec = Yaml::parse(file_get_contents(base_path(self::CHEMIN_SPEC)));

        $orphelines = array_diff_key(
            $this->operationsReelles(),
            $this->operationsDocumentees($spec),
        );

        // Releve du 2026-08-24 : 27 opérations non documentées, après ajout de
        // l'authentification étudiante et du QR du délégué. À faire baisser.
        $plafond = 27;

        $this->assertLessThanOrEqual(
            $plafond,
            count($orphelines),
            "Le nombre d'opérations non documentées est passé de {$plafond} à "
            . count($orphelines) . ". Documenter les nouvelles routes dans "
            . self::CHEMIN_SPEC . " ou relever ce plafond en conscience.\n"
            . 'Manquantes : ' . implode(', ', array_slice(array_keys($orphelines), 0, 10))
        );
    }

    public function test_les_points_d_entree_non_authentifies_sont_documentes(): void
    {
        $spec = Yaml::parse(file_get_contents(base_path(self::CHEMIN_SPEC)));
        $documentees = $this->operationsDocumentees($spec);

        // Ce sont les surfaces d'attaque : elles doivent figurer dans la
        // specification, sinon la passe de securite ne les voit pas.
        //
        // « post /presence/scan » en est sorti : il exige desormais un jeton
        // etudiant (auth:sanctum, ability:etudiant) et n'est plus un point
        // d'entree public — voir routes/api.php.
        $critiques = [
            'get /presence/course-by-token/{P}',
            'post /login',
            'post /auth/student/login',
            'get /landing/stats',
        ];

        foreach ($critiques as $operation) {
            $this->assertArrayHasKey(
                $operation,
                $documentees,
                "« {$operation} » est un point d'entrée non authentifié absent de la "
                . "spécification : la passe OWASP ne le teste donc pas."
            );
        }
    }
}
