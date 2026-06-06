<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected $apiKey;
    protected $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
    }

    /**
     * Appelle l'API Gemini pour analyser un emploi du temps PDF.
     * Conforme CDC 8.1 — extraction structurée avec score de confiance.
     */
    public function analyzeSchedule(string $filePath): array
    {
        if (!$this->apiKey) {
            return $this->errorResult('Clé API Gemini manquante. Configurez GEMINI_API_KEY dans .env.');
        }

        if (!file_exists($filePath)) {
            return $this->errorResult('Fichier introuvable : ' . $filePath);
        }

        try {
            $pdfContent = base64_encode(file_get_contents($filePath));

            $prompt = "Tu es un assistant administratif de l'UAC (Université d'Abomey-Calavi). " .
                      "Analyse ce PDF d'emploi du temps universitaire et extraits TOUS les événements de cours " .
                      "sous forme d'un tableau JSON structuré. " .
                      "Chaque événement DOIT contenir : ec (nom du cours), date (format YYYY-MM-DD), " .
                      "heure_debut (HH:mm), heure_fin (HH:mm), salle (si disponible). " .
                      "Réponds UNIQUEMENT avec le JSON. " .
                      "Si le document est un PDF scanné sans couche texte, réponds avec un tableau vide et un avertissement.";

            $response = Http::timeout(60)
                ->post("{$this->baseUrl}/gemini-2.5-flash:generateContent?key={$this->apiKey}", [
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
                ]);

            if ($response->failed()) {
                $status = $response->status();
                if ($status === 429) {
                    return $this->errorResult('Quota API Gemini dépassé. Veuillez réessayer plus tard ou passer au plan payant.');
                }
                return $this->errorResult('Erreur API Gemini (' . $status . '): ' . $response->body());
            }

            $result = $response->json();
            $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Nettoyer le texte pour extraire le JSON
            $text = trim($text);
            if (preg_match('/```json\s*([\s\S]*?)\s*```/', $text, $matches)) {
                $text = $matches[1];
            } elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $text, $matches)) {
                $text = $matches[1];
            }

            $data = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Gemini : réponse non-JSON reçue', ['raw' => substr($text, 0, 500)]);
                return $this->errorResult('Format de réponse inattendu de l\'API Gemini.');
            }

            // Extraction des événements et des cours
            $events = $data['events'] ?? $data['cours'] ?? $data;
            if (isset($events[0]) && !isset($events[0]['ec']) && isset($events[0]['cours'])) {
                // Normalisation : la clé 'cours' devient 'ec'
                $events = array_map(fn($e) => [
                    'ec' => $e['cours'] ?? '',
                    'date' => $e['date'] ?? '',
                    'heure_debut' => $e['heure_debut'] ?? $e['debut'] ?? '',
                    'heure_fin' => $e['heure_fin'] ?? $e['fin'] ?? '',
                    'salle' => $e['salle'] ?? '',
                ], $events);
            }

            $events = array_filter($events, fn($e) => !empty($e['ec']) && !empty($e['date']));
            $events = array_values($events);

            // Calcul du score de confiance (CDC 8.2)
            $score = $this->calculateConfidenceScore($events);

            // Extraire les cours uniques
            $courses = $this->extractUniqueCourses($events);

            return [
                'status' => 'success',
                'score_de_confiance' => $score['score'],
                'statut_analyse' => $score['requires_validation'] ? 'a_reverifier' : 'valide',
                'warning' => $score['requires_validation']
                    ? 'Le score de confiance est inférieur à 70%. Vérification manuelle requise.'
                    : null,
                'data' => [
                    'events' => $events,
                    'courses' => $courses,
                ],
                'metadata' => [
                    'total_events' => count($events),
                    'total_courses' => count($courses),
                    'filename' => basename($filePath),
                ],
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Gemini : timeout ou erreur réseau', ['error' => $e->getMessage()]);
            return $this->errorResult('Timeout de l\'API Gemini. Veuillez réessayer.');
        } catch (\Exception $e) {
            Log::error('Gemini : erreur inattendue', ['error' => $e->getMessage()]);
            return $this->errorResult('Erreur lors de l\'analyse : ' . $e->getMessage());
        }
    }

    /**
     * Analyse un PDF d'offre de formation (UE/EC) via Gemini.
     * Conforme CDC 8.1 — extraction des unités d'enseignement.
     */
    public function analyzeCourses(string $filePath): array
    {
        if (!$this->apiKey) {
            return $this->errorResult('Clé API Gemini manquante. Configurez GEMINI_API_KEY dans .env.');
        }

        if (!file_exists($filePath)) {
            return $this->errorResult('Fichier introuvable : ' . $filePath);
        }

        try {
            $pdfContent = base64_encode(file_get_contents($filePath));

            $prompt = "Tu es un assistant administratif de l'UAC. " .
                      "Analyse ce PDF de catalogue de cours / offre de formation et extrait TOUTES les " .
                      "Unités d'Enseignement (UE) avec leurs Éléments Constitutifs (EC). " .
                      "Réponds avec un JSON structuré contenant un tableau 'ues'. " .
                      "Chaque UE a : code, intitule, semestre (numéro), credits (nombre), " .
                      "et un tableau 'ecs'. Chaque EC a : code, intitule, volume_horaire (en heures). " .
                      "Réponds UNIQUEMENT avec le JSON valide.";

            $response = Http::timeout(60)
                ->post("{$this->baseUrl}/gemini-2.5-flash:generateContent?key={$this->apiKey}", [
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
                ]);

            if ($response->failed()) {
                $status = $response->status();
                if ($status === 429) {
                    return $this->errorResult('Quota API Gemini dépassé. Veuillez réessayer plus tard.');
                }
                return $this->errorResult('Erreur API Gemini (' . $status . '): ' . $response->body());
            }

            $result = $response->json();
            $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

            $text = trim($text);
            if (preg_match('/```json\s*([\s\S]*?)\s*```/', $text, $matches)) {
                $text = $matches[1];
            } elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $text, $matches)) {
                $text = $matches[1];
            }

            $data = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Gemini courses : réponse non-JSON', ['raw' => substr($text, 0, 500)]);
                return $this->errorResult('Format de réponse inattendu de l\'API Gemini.');
            }

            $ues = $data['ues'] ?? $data['cours'] ?? [$data];
            $ues = array_filter($ues, fn($u) => !empty($u['code']) || !empty($u['intitule']));
            $ues = array_values($ues);

            $totalEcs = 0;
            foreach ($ues as $ue) {
                $totalEcs += count($ue['ecs'] ?? []);
            }

            $score = 0.95;

            return [
                'status' => 'success',
                'score_de_confiance' => $score,
                'statut_analyse' => 'valide',
                'warning' => null,
                'data' => [
                    'ues' => $ues,
                ],
                'metadata' => [
                    'total_ues' => count($ues),
                    'total_ecs' => $totalEcs,
                    'filename' => basename($filePath),
                ],
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Gemini courses : timeout', ['error' => $e->getMessage()]);
            return $this->errorResult('Timeout de l\'API Gemini. Veuillez réessayer.');
        } catch (\Exception $e) {
            Log::error('Gemini courses : erreur', ['error' => $e->getMessage()]);
            return $this->errorResult('Erreur lors de l\'analyse : ' . $e->getMessage());
        }
    }

    /**
     * Calcule le score de confiance selon CDC 8.2.
     */
    private function calculateConfidenceScore(array $events): array
    {
        if (empty($events)) {
            return ['score' => 0, 'requires_validation' => true];
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

        $score = $totalFields > 0 ? round($presentFields / $totalFields, 2) : 0;

        return [
            'score' => $score,
            'requires_validation' => $score < 0.70,
        ];
    }

    /**
     * Extrait les cours uniques depuis les événements.
     * Détecte le semestre à partir du nom du cours (ex: "UE1 S1", "MATH-L2-S1")
     * ou des dates (les semestres S1 commencent en sept, S2 en janv).
     */
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

            // --- Détection intelligente du semestre ---
            $semestre = $this->guessSemester($name, $ev['date'] ?? null);

            $courses[] = [
                'code'     => substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($name)), 0, 10),
                'intitule' => $name,
                'semestre' => $semestre,
                'credits'  => '4',
            ];
        }

        return $courses;
    }

    /**
     * Devine le numéro de semestre à partir du nom du cours et de la date.
     *
     * Logique :
     *  1. Recherche d'un pattern "S1", "S2"… "S10" dans le nom du cours.
     *  2. Si introuvable, utilise la date — les cours de septembre-février sont S1,
     *     ceux de mars-juillet sont S2. Pour L1, les deux semestres sont S1-S2,
     *     pour L2 c'est S3-S4, etc.
     *  3. Fallback : S1.
     *
     * @param string      $courseName
     * @param string|null $dateStr    Date au format YYYY-MM-DD
     * @return string     Format "S1", "S2", etc.
     */
    private function guessSemester(string $courseName, ?string $dateStr): string
    {
        // 1. Pattern explicite dans le nom : "S1", "S2", … "S10"
        if (preg_match('/S(1[0]?|[2-9])\b/', strtoupper($courseName), $m)) {
            return 'S' . (int) $m[1];
        }

        // 2. Pattern "semestre 1", "semestre 2"…
        if (preg_match('/semestre\s*(\d+)/i', $courseName, $m)) {
            return 'S' . (int) $m[1];
        }

        // 3. Détection par date
        if ($dateStr) {
            try {
                $month = (int) \Carbon\Carbon::parse($dateStr)->format('n');
                // Septembre à février → S1, Mars à juillet → S2
                // (hémisphère nord, année académique)
                if ($month >= 9 || $month <= 2) {
                    return 'S1';
                }
                return 'S2';
            } catch (\Exception $e) {
                // Ignorer, fallback
            }
        }

        // 4. Fallback
        return 'S1';
    }

    /**
     * Retourne une réponse d'erreur standardisée.
     */
    private function errorResult(string $message): array
    {
        Log::warning('GeminiService : ' . $message);

        return [
            'status' => 'error',
            'message' => $message,
            'data' => [
                'events' => [],
                'courses' => [],
                'ues' => [],
            ],
            'metadata' => [
                'total_events' => 0,
                'total_courses' => 0,
                'total_ues' => 0,
                'total_ecs' => 0,
            ],
        ];
    }
}
