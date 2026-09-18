<?php

namespace App\Services\Providers;

use App\Contracts\AiProviderInterface;
use App\Services\Providers\Concerns\RelanceLesErreursTransitoires;
use App\Services\Schedule\ClassificateurExtraction;
use App\ValueObjects\AnalysisResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterProvider implements AiProviderInterface
{
    use RelanceLesErreursTransitoires;

    private string $apiKey;

    /** Nom du modele, lu dans la configuration et non code en dur. */
    private string $model;
    private string $baseUrl = 'https://openrouter.ai/api/v1';
    private int $timeout;
    private int $maxRetries;
    private int $maxTokens;
    private float $temperature;
    private ClassificateurExtraction $classificateur;

    public function __construct(?string $apiKey)
    {
        $this->apiKey         = $apiKey ?? '';
        $this->model          = (string) config('ai.providers.openrouter.model', 'google/gemini-2.5-flash');
        $this->timeout        = (int) config('ai.providers.openrouter.timeout', 120);
        $this->maxRetries     = (int) config('ai.providers.openrouter.max_retries', 2);
        $this->maxTokens      = (int) config('ai.providers.openrouter.max_tokens', 4096);
        $this->temperature    = (float) config('ai.providers.openrouter.temperature', 0.1);
        $this->classificateur = new ClassificateurExtraction();
    }

    public function getName(): string
    {
        return 'openrouter';
    }

    public function analyzeDocument(string $filePath, string $type): AnalysisResult
    {
        if (empty($this->apiKey)) {
            return AnalysisResult::failed('Clé API OpenRouter manquante.');
        }

        try {
            $pdfContent = base64_encode(file_get_contents($filePath));
            $prompt = $this->getPrompt($type);

            ['reponse' => $response, 'erreur' => $erreur] = $this->appelerAvecRelance(
                fn () => Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => config('app.url'),
                ])
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $prompt],
                                ['type' => 'image_url', 'image_url' => ['url' => "data:application/pdf;base64,{$pdfContent}"]],
                            ],
                        ],
                    ],
                    'temperature' => $this->temperature,
                    'max_tokens'  => $this->maxTokens,
                ]),
                $this->maxRetries,
                'OpenRouter',
            );

            if ($erreur !== null) {
                return AnalysisResult::failed($erreur);
            }

            $content = $response->json('choices.0.message.content', '');
            return $this->parseResponse($content, $type, $filePath);
        } catch (\Exception $e) {
            Log::error('OpenRouter : erreur', ['error' => $e->getMessage()]);
            return AnalysisResult::failed('Erreur OpenRouter : ' . $e->getMessage());
        }
    }

    private function getPrompt(string $type): string
    {
        return match ($type) {
            'schedule' => "Analyse ce PDF d'emploi du temps universitaire. " .
                          "Extrais TOUS les événements sous forme d'un tableau JSON avec : " .
                          "ec (nom du cours), date (YYYY-MM-DD), heure_debut (HH:mm), heure_fin (HH:mm), salle. " .
                          "Réponds UNIQUEMENT avec le JSON.",
            'courses'  => ConsignesMaquette::cours(),
            default    => "Extrais les informations structurées de ce document.",
        };
    }

    private function parseResponse(string $content, string $type, string $filePath): AnalysisResult
    {
        $content = trim($content);
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        } elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return AnalysisResult::failed('Format de réponse inattendu d\'OpenRouter.');
        }

        if ($type === 'schedule') {
            // Même classificateur que Gemini : la confiance reflète la
            // proportion de créneaux effectivement compris, pas un chiffre
            // fixe qui ne dépendait ni du document ni de la réponse.
            $diagnostic = $this->classificateur->classer(array_values($data['events'] ?? []), $filePath);
            $creneaux = $diagnostic->retenus;
            $total = count($creneaux) + count($diagnostic->ecartes);
            $confidence = $total === 0 ? 0.0 : round(count($creneaux) / $total, 2);

            return AnalysisResult::completed(
                data: ['events' => $creneaux, 'courses' => [], 'diagnostic' => $diagnostic->toArray()],
                confidence: $confidence,
                warning: $diagnostic->estExploitable() && $confidence >= 0.70 ? null : $diagnostic->message(),
                metadata: ['filename' => basename($filePath), 'total_events' => count($creneaux)],
            );
        }

        $ues = $data['ues'] ?? [];

        return AnalysisResult::completed(
            data: ['ues' => $ues],
            // Pas de signal fiable par UE/EC pour juger l'extraction d'une
            // maquette (contrairement au ratio retenus/écartés de l'emploi du
            // temps) : sous le seuil de validation humaine (0,70) à dessein,
            // plutôt qu'un chiffre inventé qui la contournerait.
            confidence: 0.69,
            metadata: ['filename' => basename($filePath), 'total_ues' => count($ues)],
        );
    }
}
