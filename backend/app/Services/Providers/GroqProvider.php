<?php

namespace App\Services\Providers;

use App\Contracts\AiProviderInterface;
use App\ValueObjects\AnalysisResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqProvider implements AiProviderInterface
{
    private string $apiKey;

    /** Nom du modele, lu dans la configuration et non code en dur. */
    private string $model;
    private string $baseUrl = 'https://api.groq.com/openai/v1';

    public function __construct(?string $apiKey)
    {
        $this->apiKey = $apiKey ?? '';
        $this->model  = (string) config('ai.providers.groq.model', 'llama-3.3-70b-versatile');
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
                // Un message unique couvrait deux causes sans rapport, et
                // n'indiquait de remede pour aucune des deux : soit l'outil
                // d'extraction manque a l'installation, soit le document est un
                // scan sans couche texte. On les distingue.
                return AnalysisResult::failed($this->diagnostiquerEchecExtraction($filePath));
            }

            $prompt = $this->buildPrompt($type, $text);

            $response = Http::withToken($this->apiKey)
                ->timeout(120)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
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
        if (!$this->pdftotextDisponible()) {
            return '';
        }

        $cmd = 'pdftotext ' . escapeshellarg($filePath) . ' - 2>/dev/null';
        $output = shell_exec($cmd);

        if ($output && strlen(trim($output)) > 50) {
            return trim($output);
        }

        return '';
    }

    /**
     * L'outil d'extraction est-il installé ?
     *
     * Il vient du paquet poppler-utils, absent de l'image Docker de production.
     * Groq est donc inutilisable en production tant que ce paquet n'y est pas :
     * la distinction est ce qui permet de le dire, au lieu d'accuser le document.
     */
    private function pdftotextDisponible(): bool
    {
        return !empty(shell_exec('command -v pdftotext 2>/dev/null'));
    }

    /**
     * Pourquoi l'extraction a échoué, et que faire.
     *
     * Le message précédent — « Impossible d'extraire le texte du PDF » — était
     * exact et sans usage : il ne distinguait pas un outil manquant d'un
     * document scanné, et n'indiquait de remède pour ni l'un ni l'autre.
     */
    private function diagnostiquerEchecExtraction(string $filePath): string
    {
        if (!$this->pdftotextDisponible()) {
            return self::messageOutilManquant();
        }

        $pages = trim((string) shell_exec(
            'pdfinfo ' . escapeshellarg($filePath) . " 2>/dev/null | grep -i '^Pages' | tr -dc '0-9'"
        ));

        return self::messageDocumentScanne($pages === '' ? null : (int) $pages);
    }

    /**
     * Messages séparés de leur détection, pour être vérifiables sans dépendre
     * de ce que la machine de test a ou non d'installé.
     */
    public static function messageOutilManquant(): string
    {
        return "L'outil d'extraction de texte (pdftotext, paquet poppler-utils) n'est pas "
            . "installé sur le serveur. Installez-le, ou basculez AI_PROVIDER sur « gemini », "
            . 'qui lit le PDF sans outil externe.';
    }

    public static function messageDocumentScanne(?int $pages = null): string
    {
        return 'Ce PDF ne contient aucun texte : c\'est une image, généralement un document '
            . 'passé au scanner' . ($pages !== null ? " ({$pages} page(s))" : '') . '. Le fournisseur '
            . '« groq » ne sait analyser que du texte. Basculez AI_PROVIDER sur « gemini », qui lit '
            . "l'image du document, ou fournissez un PDF natif plutôt qu'un scan.";
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
