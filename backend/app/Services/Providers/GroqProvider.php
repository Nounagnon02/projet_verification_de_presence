<?php

namespace App\Services\Providers;

use App\Contracts\AiProviderInterface;
use App\ValueObjects\AnalysisResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $baseUrl = 'https://api.groq.com/openai/v1';

    public function __construct(?string $apiKey)
    {
        $this->apiKey = $apiKey ?? '';
    }

    public function getName(): string
    {
        return 'groq';
    }

    public function analyzeDocument(string $filePath, string $type): AnalysisResult
    {
        if (empty($this->apiKey)) {
            return AnalysisResult::failed('Clé API Groq manquante.');
        }

        // Note : Groq ne supporte pas nativement l'analyse de PDF avec vision.
        // Cette implémentation extrait le texte du PDF et l'envoie comme contexte
        // à un modèle texte (Mixtral, Llama).
        try {
            // Lire le contenu du PDF (texte uniquement)
            $text = $this->extractTextFromPdf($filePath);
            if (empty($text)) {
                return AnalysisResult::failed('Impossible d\'extraire le texte du PDF.');
            }

            $prompt = $this->buildPrompt($type, $text);

            $response = Http::withToken($this->apiKey)
                ->timeout(120)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => 'mixtral-8x7b-32768',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Tu es un assistant administratif qui extrait des données structurées à partir de documents académiques. Réponds UNIQUEMENT avec du JSON valide.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.1,
                    'max_tokens'  => 4096,
                ]);

            if (!$response->successful()) {
                return AnalysisResult::failed('Erreur API Groq : ' . $response->body());
            }

            $content = $response->json('choices.0.message.content', '');
            return $this->parseResponse($content, $type, $filePath);
        } catch (\Exception $e) {
            Log::error('Groq : erreur', ['error' => $e->getMessage()]);
            return AnalysisResult::failed('Erreur Groq : ' . $e->getMessage());
        }
    }

    private function extractTextFromPdf(string $filePath): string
    {
        // Tentative d'extraction via pdftotext (si disponible)
        $output = '';
        $cmd = "pdftotext " . escapeshellarg($filePath) . " - 2>/dev/null";
        $output = shell_exec($cmd);
        if ($output && strlen(trim($output)) > 50) {
            return trim($output);
        }

        // Fallback : retourner un message indiquant que le PDF n'a pas pu être extrait
        return '';
    }

    private function buildPrompt(string $type, string $text): string
    {
        $instruction = match ($type) {
            'schedule' => "Extrais tous les événements de cours depuis ce texte d'emploi du temps. " .
                          "Retourne un JSON avec un tableau 'events'. Chaque événement a : ec, date, heure_debut, heure_fin, salle.",
            'courses'  => "Extrais toutes les Unités d'Enseignement (UE) avec leurs Éléments Constitutifs (EC). " .
                          "Retourne un JSON avec un tableau 'ues'. Chaque UE a : code, intitule, semestre, credits, ecs[]. " .
                          "Chaque EC a : code, intitule, volume_horaire.",
            default    => "Extrais les informations structurées de ce texte académique.",
        };

        return $instruction . "\n\nTexte du document :\n" . substr($text, 0, 15000);
    }

    private function parseResponse(string $content, string $type, string $filePath): AnalysisResult
    {
        // Nettoyer le JSON
        $content = trim($content);
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        } elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return AnalysisResult::failed('Format de réponse inattendu de Groq.');
        }

        if ($type === 'schedule') {
            $events = $data['events'] ?? [];
            return AnalysisResult::completed(
                data: ['events' => $events, 'courses' => []],
                confidence: 0.8,
                metadata: ['filename' => basename($filePath), 'total_events' => count($events)],
            );
        }

        $ues = $data['ues'] ?? [];
        return AnalysisResult::completed(
            data: ['ues' => $ues],
            confidence: 0.8,
            metadata: ['filename' => basename($filePath), 'total_ues' => count($ues)],
        );
    }
}
