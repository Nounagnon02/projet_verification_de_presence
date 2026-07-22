<?php

namespace App\Services\Providers;

use App\Contracts\AiProviderInterface;
use App\ValueObjects\AnalysisResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(?string $apiKey)
    {
        $this->apiKey = $apiKey ?? '';
    }

    public function getName(): string
    {
        return 'gemini';
    }

    public function analyzeDocument(string $filePath, string $type): AnalysisResult
    {
        if (empty($this->apiKey)) {
            return AnalysisResult::failed('Clé API Gemini manquante.');
        }

        if (!file_exists($filePath)) {
            return AnalysisResult::failed("Fichier introuvable : {$filePath}");
        }

        try {
            $pdfContent = base64_encode(file_get_contents($filePath));

            $prompt = match ($type) {
                'schedule' => $this->getSchedulePrompt(),
                'courses'  => $this->getCoursesPrompt(),
                default    => throw new \InvalidArgumentException("Type inconnu : {$type}"),
            };

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            ['inline_data' => [
                                'mime_type' => 'application/pdf',
                                'data'      => $pdfContent,
                            ]],
                        ],
                    ],
                ],
            ];

            $response = $this->callWithRetry($payload);

            if (isset($response['status']) && $response['status'] === 'error') {
                return AnalysisResult::failed($response['message'] ?? 'Erreur API Gemini');
            }

            $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Nettoyer le JSON
            $text = trim($text);
            if (preg_match('/```json\s*([\s\S]*?)\s*```/', $text, $matches)) {
                $text = $matches[1];
            } elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $text, $matches)) {
                $text = $matches[1];
            }

            $data = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Gemini : réponse non-JSON', ['raw' => substr($text, 0, 500)]);
                return AnalysisResult::failed('Format de réponse inattendu de l\'API Gemini.');
            }

            if ($type === 'schedule') {
                return $this->processScheduleResult($data, $filePath);
            }

            return $this->processCoursesResult($data, $filePath);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return AnalysisResult::failed('Timeout de l\'API Gemini.');
        } catch (\Exception $e) {
            Log::error('Gemini : erreur', ['error' => $e->getMessage()]);
            return AnalysisResult::failed('Erreur lors de l\'analyse : ' . $e->getMessage());
        }
    }

    private function callWithRetry(array $payload, int $maxRetries = 3): array
    {
        $attempt = 0;
        $delay = 1000;

        while ($attempt <= $maxRetries) {
            try {
                $response = Http::timeout(60)
                    ->post("{$this->baseUrl}/gemini-2.0-flash:generateContent?key={$this->apiKey}", $payload);

                if ($response->successful()) {
                    return $response->json();
                }

                $status = $response->status();

                if ($status === 429) {
                    $attempt++;
                    if ($attempt > $maxRetries) {
                        return $this->errorResult('Quota API Gemini dépassé.');
                    }
                    Log::warning("Gemini : quota dépassé, tentative {$attempt}/{$maxRetries}");
                    usleep($delay * 1000);
                    $delay *= 2;
                    continue;
                }

                if ($status >= 500) {
                    $attempt++;
                    if ($attempt > $maxRetries) {
                        return $this->errorResult('Erreur serveur Gemini.');
                    }
                    usleep($delay * 1000);
                    $delay *= 2;
                    continue;
                }

                return $this->errorResult("Erreur API Gemini ({$status}): " . $response->body());
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $attempt++;
                if ($attempt > $maxRetries) {
                    return $this->errorResult('Timeout API Gemini après ' . $maxRetries . ' tentatives.');
                }
                usleep($delay * 1000);
                $delay *= 2;
            }
        }

        return $this->errorResult('Erreur inconnue lors de l\'appel API Gemini.');
    }

    private function processScheduleResult(array $data, string $filePath): AnalysisResult
    {
        $events = $data['events'] ?? $data['cours'] ?? $data;
        if (isset($events[0]) && !isset($events[0]['ec']) && isset($events[0]['cours'])) {
            $events = array_map(fn($e) => [
                'ec'          => $e['cours'] ?? '',
                'date'        => $e['date'] ?? '',
                'heure_debut' => $e['heure_debut'] ?? $e['debut'] ?? '',
                'heure_fin'   => $e['heure_fin'] ?? $e['fin'] ?? '',
                'salle'       => $e['salle'] ?? '',
            ], $events);
        }

        $events = array_values(array_filter($events, fn($e) => !empty($e['ec']) && !empty($e['date'])));

        $confidence = $this->calculateConfidence($events);
        $courses = $this->extractUniqueCourses($events);

        return AnalysisResult::completed(
            data: [
                'events'  => $events,
                'courses' => $courses,
            ],
            confidence: $confidence,
            warning: $confidence < 0.70 ? 'Le score de confiance est inférieur à 70%. Vérification manuelle requise.' : null,
            metadata: [
                'total_events'  => count($events),
                'total_courses' => count($courses),
                'filename'      => basename($filePath),
            ],
        );
    }

    private function processCoursesResult(array $data, string $filePath): AnalysisResult
    {
        $ues = $data['ues'] ?? $data['cours'] ?? [$data];
        $ues = array_values(array_filter($ues, fn($u) => !empty($u['code']) || !empty($u['intitule'])));

        $totalEcs = 0;
        foreach ($ues as $ue) {
            $totalEcs += count($ue['ecs'] ?? []);
        }

        return AnalysisResult::completed(
            data: ['ues' => $ues],
            confidence: 0.95,
            metadata: [
                'total_ues' => count($ues),
                'total_ecs' => $totalEcs,
                'filename'  => basename($filePath),
            ],
        );
    }

    private function calculateConfidence(array $events): float
    {
        if (empty($events)) {
            return 0.0;
        }

        $totalFields = 0;
        $presentFields = 0;
        $expectedKeys = ['ec', 'date', 'heure_debut', 'heure_fin', 'salle'];

        foreach ($events as $event) {
            foreach ($expectedKeys as $key) {
                $totalFields++;
                if (isset($event[$key]) && !empty($event[$key])) {
                    $presentFields++;
                }
            }
        }

        return $totalFields > 0 ? round($presentFields / $totalFields, 2) : 0;
    }

    private function extractUniqueCourses(array $events): array
    {
        $seen = [];
        $courses = [];

        foreach ($events as $ev) {
            $name = $ev['ec'] ?? '';
            if (!$name || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;

            $courses[] = [
                'code'     => substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($name)), 0, 10),
                'intitule' => $name,
                'semestre' => 'S1',
                'credits'  => '4',
            ];
        }

        return $courses;
    }

    private function getSchedulePrompt(): string
    {
        return "Tu es un assistant administratif de l'UAC (Université d'Abomey-Calavi). " .
               "Analyse ce PDF d'emploi du temps universitaire et extraits TOUS les événements de cours " .
               "sous forme d'un tableau JSON structuré. " .
               "Chaque événement DOIT contenir : ec (nom du cours), date (format YYYY-MM-DD), " .
               "heure_debut (HH:mm), heure_fin (HH:mm), salle (si disponible). " .
               "Réponds UNIQUEMENT avec le JSON. " .
               "Si le document est un PDF scanné sans couche texte, réponds avec un tableau vide et un avertissement.";
    }

    private function getCoursesPrompt(): string
    {
        return "Tu es un assistant administratif de l'UAC. " .
               "Analyse ce PDF de catalogue de cours / offre de formation et extrait TOUTES les " .
               "Unités d'Enseignement (UE) avec leurs Éléments Constitutifs (EC). " .
               "Réponds avec un JSON structuré contenant un tableau 'ues'. " .
               "Chaque UE a : code, intitule, semestre (numéro), credits (nombre), " .
               "et un tableau 'ecs'. Chaque EC a : code, intitule, volume_horaire (en heures). " .
               "Réponds UNIQUEMENT avec le JSON valide.";
    }

    private function errorResult(string $message): array
    {
        Log::warning('GeminiProvider : ' . $message);
        return ['status' => 'error', 'message' => $message];
    }
}
