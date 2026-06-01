<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected $apiKey;
    protected $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
    }

    public function analyzeSchedule($filePath)
    {
        if (!$this->apiKey) {
            return ['status' => 'error', 'message' => 'API Key Gemini manquante.', 'data' => []];
        }

        $prompt = "Tu es un assistant administratif de l'UAC. Analyse ce PDF d'emploi du temps et extrait les événements sous forme de JSON structuré.
        Chaque événement doit avoir: ec (nom du cours), date (YYYY-MM-DD), heure_debut (HH:mm), heure_fin (HH:mm), salle.
        Répond uniquement avec le JSON.";

        Log::info("Gemini analysis prompt sent for file: " . $filePath);

        // Simulation de la réponse de l'API — événements + cours
        $events = [
            [
                'ec' => 'Algorithmique Avancée',
                'code' => 'INF3012',
                'date' => date('Y-m-d', strtotime('next Monday')),
                'heure_debut' => '08:00',
                'heure_fin' => '10:00',
                'salle' => 'Amphi I',
            ],
            [
                'ec' => 'Base de Données',
                'code' => 'INF2001',
                'date' => date('Y-m-d', strtotime('next Tuesday')),
                'heure_debut' => '10:00',
                'heure_fin' => '12:00',
                'salle' => 'Labo 1',
            ],
            [
                'ec' => 'Probabilités et Statistiques',
                'code' => 'MAT2040',
                'date' => date('Y-m-d', strtotime('next Wednesday')),
                'heure_debut' => '14:00',
                'heure_fin' => '16:00',
                'salle' => 'Salle 201',
            ],
            [
                'ec' => 'Systèmes d\'Information',
                'code' => 'INF3005',
                'date' => date('Y-m-d', strtotime('next Thursday')),
                'heure_debut' => '09:00',
                'heure_fin' => '11:00',
                // salle manquante pour simulation de score
            ],
        ];

        // Extraire les cours uniques depuis les événements
        $seen = [];
        $courses = [];
        foreach ($events as $ev) {
            $name = $ev['ec'] ?? '';
            $code = $ev['code'] ?? '';
            if ($name && !isset($seen[$name])) {
                $seen[$name] = true;
                $courses[] = [
                    'code' => $code,
                    'intitule' => $name,
                    'semestre' => 'S' . (rand(1, 6)),
                    'credits' => (string) rand(3, 6),
                ];
            }
        }

        // Calcul du score de confiance (CDC 8.2)
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
        $requiresValidation = $score < 0.70;

        return [
            'status' => 'success',
            'score_de_confiance' => $score,
            'statut_analyse' => $requiresValidation ? 'a_reverifier' : 'valide',
            'warning' => $requiresValidation ? 'Le score de confiance est inférieur à 70%. Vérification manuelle requise.' : null,
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
    }

    public function analyzeCourses($filePath)
    {
        if (!$this->apiKey) {
            return ['status' => 'error', 'message' => 'API Key Gemini manquante.', 'data' => []];
        }

        $prompt = "Tu es un assistant administratif de l'UAC. Analyse ce PDF de catalogue de cours et extrait les Unités d'Enseignement (UE) avec leurs Éléments Constitutifs (EC). " .
                  "Répond avec un JSON structuré contenant un tableau 'ues'. Chaque UE a: code, intitule, semestre, credits, et un tableau 'ecs'. " .
                  "Chaque EC a: code, intitule, volume_horaire. Répond uniquement avec le JSON.";

        Log::info("Gemini course analysis prompt sent for file: " . $filePath);

        // Simulation de la réponse de l'API
        $ues = [
            [
                'code' => 'UE-INF-301',
                'intitule' => 'Algorithmique et Structures de Données',
                'semestre' => 3,
                'credits' => 6,
                'ecs' => [
                    ['code' => 'INF3011', 'intitule' => 'Algorithmique Avancée', 'volume_horaire' => 45],
                    ['code' => 'INF3012', 'intitule' => 'Structures de Données', 'volume_horaire' => 30],
                ],
            ],
            [
                'code' => 'UE-INF-201',
                'intitule' => 'Bases de Données',
                'semestre' => 4,
                'credits' => 5,
                'ecs' => [
                    ['code' => 'INF2001', 'intitule' => 'Base de Données Relationnelles', 'volume_horaire' => 40],
                    ['code' => 'INF2002', 'intitule' => 'SQL et Optimisation', 'volume_horaire' => 20],
                ],
            ],
            [
                'code' => 'UE-MATH-202',
                'intitule' => 'Mathématiques pour l\'Informatique',
                'semestre' => 2,
                'credits' => 5,
                'ecs' => [
                    ['code' => 'MAT2040', 'intitule' => 'Probabilités et Statistiques', 'volume_horaire' => 35],
                ],
            ],
        ];

        $totalEcs = 0;
        foreach ($ues as $ue) {
            $totalEcs += count($ue['ecs']);
        }

        $score = 0.95; // Score de confiance simulé

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
    }
}
