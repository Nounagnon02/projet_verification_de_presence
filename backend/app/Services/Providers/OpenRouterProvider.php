<?php

namespace App\Services\Providers;

use App\Contracts\AiProviderInterface;
use App\ValueObjects\AnalysisResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $baseUrl = 'https://openrouter.ai/api/v1';

    public function __construct(?string $apiKey)
    {
        $this->apiKey = $apiKey ?? '';
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

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
            ])
            ->timeout(120)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => 'google/gemini-2.5-flash-preview-04-17',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => "data:application/pdf;base64,{$pdfContent}"]],
                        ],
                    ],
                ],
                'temperature' => 0.1,
                'max_tokens'  => 4096,
            ]);

            if (!$response->successful()) {
                return AnalysisResult::failed('Erreur API OpenRouter : ' . $response->body());
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
            'courses'  => "Analyse ce PDF de catalogue de cours. " .
                          "Extrais toutes les UE avec leurs ECs sous forme d'un tableau JSON 'ues'. " .
                          "Chaque UE a : code, intitule, semestre, credits, ecs[]. " .
                          "Chaque EC a : code, intitule, volume_horaire. " .
                          "Réponds UNIQUEMENT avec le JSON.",
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
            $events = $data['events'] ?? [];
            return AnalysisResult::completed(
                data: ['events' => $events, 'courses' => []],
                confidence: 0.85,
                metadata: ['filename' => basename($filePath), 'total_events' => count($events)],
            );
        }

        $ues = $data['ues'] ?? [];
        return AnalysisResult::completed(
            data: ['ues' => $ues],
            confidence: 0.85,
            metadata: ['filename' => basename($filePath), 'total_ues' => count($ues)],
        );
    }
}
