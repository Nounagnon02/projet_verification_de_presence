<?php

namespace App\Services\Providers;

use App\Contracts\AiProviderInterface;
use App\ValueObjects\AnalysisResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AiProviderInterface
{
    private string $apiKey;

    /**
     * Nom du modèle, lu dans la configuration.
     *
     * Il était codé en dur : « gemini-2.0-flash » a été retiré par Google, qui
     * répond alors 404, et toute analyse échouait.
     */
    private string $model;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    /**
     * Le classificateur remplace l'ancien filtre de sortie, qui ecartait en
     * silence tout creneau sans date calendaire.
     */
    private \App\Services\Schedule\ClassificateurExtraction $classificateur;

    public function __construct(?string $apiKey)
    {
        $this->classificateur = new \App\Services\Schedule\ClassificateurExtraction();
        $this->apiKey = $apiKey ?? '';
        $this->model  = (string) config('ai.providers.gemini.model', 'gemini-3.6-flash');
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
                // 60 s etait le delai le plus court des trois fournisseurs, alors
                // que gemini fait le plus gros travail : il recoit le PDF entier
                // et dechiffre lui-meme les pages scannees, la ou groq et
                // openrouter ne recoivent que du texte deja extrait — avec
                // 120 s chacun.
                //
                // Mesure du 2026-08-26 sur une offre de formation scannee de
                // deux pages : 45 s, soit 75 % du budget. Quatre pages
                // depassaient, et ProcessAiImportJob reessayait trois fois pour
                // le meme resultat. Le job dispose de 300 s : 180 s laissent une
                // marge reelle sans jamais le faire tuer en vol.
                $response = Http::timeout(180)
                    ->post("{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}", $payload);

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

        // ANCIEN CODE, cause directe du defaut :
        //
        //   array_filter($events, fn($e) => !empty($e['ec']) && !empty($e['date']))
        //
        // Tout creneau sans date calendaire etait ecarte EN SILENCE. Les emplois du
        // temps hebdomadaires — la forme habituelle a l'UAC — n'en ont aucune : ils
        // rendaient donc systematiquement vide, et l'utilisateur ne voyait qu'un
        // ecran sans creneaux, sans rien qui distingue un document vide d'un
        // document dont le contenu avait ete jete.
        //
        // Le classificateur remplace ce filtre : il normalise ce qui peut l'etre,
        // conserve le MOTIF de chaque ecart, et observe le document pour dire si
        // celui-ci est reellement vide.
        $diagnostic = $this->classificateur->classer(array_values($events), $filePath);

        $creneaux = $diagnostic->retenus;
        $courses  = $this->extractUniqueCourses($creneaux);

        // La confiance ne peut plus se deduire du seul nombre de creneaux : un
        // document dont la moitie des creneaux a ete ecartee n'est pas fiable a
        // moitie, il demande une relecture. Elle reflete donc la proportion
        // effectivement comprise.
        $total = count($creneaux) + count($diagnostic->ecartes);
        $confidence = $total === 0 ? 0.0 : round(count($creneaux) / $total, 2);

        return AnalysisResult::completed(
            data: [
                // « events » reste la cle attendue par les ecrans existants.
                'events'     => $creneaux,
                'courses'    => $courses,
                'diagnostic' => $diagnostic->toArray(),
            ],
            confidence: $confidence,
            warning: $diagnostic->estExploitable() && $confidence >= 0.70
                ? null
                : $diagnostic->message(),
            metadata: [
                'statut'        => $diagnostic->statut,
                'total_events'  => count($creneaux),
                'total_ecartes' => count($diagnostic->ecartes),
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

    /**
     * Consigne d'extraction d'un emploi du temps.
     *
     * L'ancienne version exigeait « date (format YYYY-MM-DD) ». Or l'emploi du
     * temps universitaire habituel est HEBDOMADAIRE : ses colonnes sont
     * « Lundi … Samedi », ses lignes des bandes horaires « 8h – 13h ». Aucune
     * date. Le modèle suivait donc correctement la consigne et rendait un tableau
     * vide — sur les quatre documents hebdomadaires du corpus, mesuré à 0 %.
     *
     * La consigne accepte désormais les DEUX formes et interdit explicitement
     * d'inventer une date absente : un créneau daté à tort crée un cours fantôme,
     * donc des absences pour des étudiants réels.
     */
    private function getSchedulePrompt(): string
    {
        return implode("\n", [
            "Tu es un assistant administratif de l'Universite d'Abomey-Calavi.",
            "Analyse ce PDF d'emploi du temps et extrais TOUS les creneaux de cours en JSON.",
            "",
            "Chaque creneau porte :",
            "- ec : intitule du cours tel qu'ecrit dans le document (obligatoire) ;",
            "- ec_code : le code de l'EC s'il figure dans le document, sinon omets le champ ;",
            "- jour : le jour de la semaine tel qu'ecrit (Lundi, Mardi...) si le document est",
            "  un emploi du temps hebdomadaire ;",
            "- date : au format YYYY-MM-DD UNIQUEMENT si le document donne une date calendaire",
            "  explicite. N'INVENTE JAMAIS de date : si le document ne donne qu'un jour de la",
            "  semaine, renseigne « jour » et OMETS « date » ;",
            "- heure_debut et heure_fin : recopie la notation du document (8h, 08:00, 14h30)",
            "  sans la convertir ;",
            "- salle : le lieu s'il est indique ;",
            "- enseignant : le ou les enseignants s'ils sont indiques, separes par / ;",
            "- filiere : la filiere ou le groupe si la cellule le precise. Un meme document",
            "  peut couvrir plusieurs filieres, chaque cellule indiquant la sienne ;",
            "- type_seance : cours, TD, TP, evaluation... si le document le precise.",
            "",
            "REGLES ABSOLUES :",
            "1. N'invente aucune donnee. Un champ absent du document est omis, jamais devine.",
            "2. Une cellule vide n'est pas un creneau : ne la rapporte pas.",
            "3. Si une cellule empile plusieurs informations (filiere, intitule, volume, salle,",
            "   enseignant), separe-les dans les champs correspondants.",
            "4. Si le document ne contient aucun emploi du temps, renvoie un tableau vide.",
            "5. Si le document est un scan sans couche texte, renvoie un tableau vide.",
            "",
            'Reponds UNIQUEMENT avec le JSON, sous la forme {"events": [ ... ]}.',
        ]);
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
