<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;

class ApiDocumentationController extends Controller
{
    /**
     * Affiche l'interface Swagger UI pour la documentation API
     *
     * GET /api/docs
     */
    public function index(): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - Système Vérification Présence UAC</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui.css" />
    <style>
        html, body { margin: 0; padding: 0; height: 100%; }
        .swagger-ui .topbar { display: none; }
        .swagger-ui .info { margin: 20px 0; }
        .swagger-ui .info .title { color: #1e40af; font-size: 2.5rem; }
        .swagger-ui .info .description { font-size: 1rem; line-height: 1.6; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: '/api/docs/json',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                tryItOutEnabled: true,
                requestInterceptor: (req) => {
                    // Ajouter le token CSRF pour les requêtes try-it-out
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                    if (csrfToken) {
                        req.headers['X-CSRF-TOKEN'] = csrfToken;
                    }
                    // Ajouter le cookie de session (Sanctum)
                    req.credentials = 'include';
                    return req;
                },
                responseInterceptor: (response) => {
                    return response;
                }
            });
            window.ui = ui;
        };
    </script>
</body>
</html>
HTML;

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Retourne la spécification OpenAPI en JSON
     *
     * GET /api/docs/json
     */
    public function json(): JsonResponse
    {
        $specPath = base_path('docs/openapi.json');

        if (!File::exists($specPath)) {
            // Générer à la volée depuis le YAML si le JSON n'existe pas
            $yamlPath = base_path('docs/openapi.yaml');
            if (File::exists($yamlPath)) {
                $yaml = File::get($yamlPath);
                $spec = \Symfony\Component\Yaml\Yaml::parse($yaml);
                return response()->json($spec);
            }

            return response()->json([
                'success' => false,
                'message' => 'Documentation OpenAPI non trouvée. Générez-la d\'abord.',
            ], 404);
        }

        $spec = File::get($specPath);
        return response()->json(json_decode($spec, true));
    }

    /**
     * Retourne la spécification OpenAPI en YAML
     *
     * GET /api/docs/yaml
     */
    public function yaml(): Response
    {
        $specPath = base_path('docs/openapi.yaml');

        if (!File::exists($specPath)) {
            return response('Documentation OpenAPI non trouvée.', 404)
                ->header('Content-Type', 'text/plain');
        }

        $yaml = File::get($specPath);
        return response($yaml, 200)
            ->header('Content-Type', 'application/x-yaml');
    }
}