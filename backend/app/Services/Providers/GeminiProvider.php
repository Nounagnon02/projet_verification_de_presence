<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Contracts\AiProviderInterface;
use App\Services\Providers\Concerns\RelanceLesErreursTransitoires;
use App\ValueObjects\AnalysisResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AiProviderInterface
{
    use RelanceLesErreursTransitoires;

    private string $apiKey;

    /**
     * Nom du modèle, lu dans la configuration.
     *
     * Il était codé en dur : « gemini-2.0-flash » a été retiré par Google, qui
     * répond alors 404, et toute analyse échouait.
     */
    private string $model;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';
    private int $timeout;
    private int $maxRetries;

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
        // 60 s etait le delai le plus court des trois fournisseurs, alors que
        // Gemini fait le plus gros travail : il recoit le PDF entier et
        // dechiffre lui-meme les pages scannees, la ou groq et openrouter ne
        // recoivent que du texte deja extrait.
        //
        // Mesure du 2026-08-26 sur une offre de formation scannee de deux
        // pages : 45 s, soit 75 % du budget. Quatre pages depassaient, et
        // ProcessAiImportJob reessayait trois fois pour le meme resultat. Le
        // job dispose de 300 s : 180 s laissent une marge reelle sans jamais
        // le faire tuer en vol.
        $this->timeout    = (int) config('ai.providers.gemini.timeout', 180);
        $this->maxRetries = (int) config('ai.providers.gemini.max_retries', 3);
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
                // responseSchema contraint la FORME de la réponse (JSON plutôt
                // qu'un bloc ```json``` noyé dans du texte), pas son CONTENU :
                // seul « ec » est requis. Rendre les autres champs requis aurait
                // forcé le modèle à en inventer un quand le document ne le
                // donne pas — l'inverse de la consigne « n'invente aucune donnée ».
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema'   => $this->schemaPourType($type),
                ],
            ];

            ['reponse' => $response, 'erreur' => $erreur] = $this->appelerAvecRelance(
                fn () => Http::timeout($this->timeout)
                    ->post("{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}", $payload),
                $this->maxRetries,
                'Gemini',
            );

            if ($erreur !== null) {
                return AnalysisResult::failed($erreur);
            }

            $donnees = $response->json();
            $text = $donnees['candidates'][0]['content']['parts'][0]['text'] ?? '';

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
        } catch (\Exception $e) {
            Log::error('Gemini : erreur', ['error' => $e->getMessage()]);
            return AnalysisResult::failed('Erreur lors de l\'analyse : ' . $e->getMessage());
        }
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
                // Version du document, à partir de laquelle il s'applique.
                'valide_du'  => self::dateDuDocument($data['valide_du'] ?? null),
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

    /** Date lue au niveau du document, retenue seulement si c'est une vraie date AAAA-MM-JJ. */
    private static function dateDuDocument(mixed $valeur): ?string
    {
        if (!is_string($valeur) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valeur, $m)) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $valeur : null;
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
            // Pas de signal fiable par UE/EC pour juger l'extraction d'une
            // maquette (contrairement au ratio retenus/écartés de l'emploi du
            // temps, ci-dessus) : sous le seuil de validation humaine (0,70) à
            // dessein, plutôt qu'un chiffre inventé qui la contournerait.
            confidence: 0.69,
            metadata: [
                'total_ues' => count($ues),
                'total_ecs' => $totalEcs,
                'filename'  => basename($filePath),
            ],
        );
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
    /**
     * Schéma de sortie Gemini (sous-ensemble d'OpenAPI 3.0), par type
     * d'analyse. Seuls les champs que le document donne TOUJOURS sont requis :
     * le reste doit rester omissible, pour ne pas contredire la consigne
     * « n'invente aucune donnée » des deux prompts.
     */
    private function schemaPourType(string $type): array
    {
        return match ($type) {
            'schedule' => [
                'type'       => 'OBJECT',
                'properties' => [
                    'events' => [
                        'type'  => 'ARRAY',
                        'items' => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'ec'          => ['type' => 'STRING'],
                                'ec_code'     => ['type' => 'STRING'],
                                'jour'        => ['type' => 'STRING'],
                                'date'        => ['type' => 'STRING'],
                                'heure_debut' => ['type' => 'STRING'],
                                'heure_fin'   => ['type' => 'STRING'],
                                'salle'       => ['type' => 'STRING'],
                                'enseignant'  => ['type' => 'STRING'],
                                'filiere'     => ['type' => 'STRING'],
                                'type_seance' => ['type' => 'STRING'],
                            ],
                            'required' => ['ec'],
                        ],
                    ],
                    'valide_du' => ['type' => 'STRING'],
                ],
                'required' => ['events'],
            ],
            'courses' => [
                'type'       => 'OBJECT',
                'properties' => [
                    'ues' => [
                        'type'  => 'ARRAY',
                        'items' => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'code'     => ['type' => 'STRING'],
                                'intitule' => ['type' => 'STRING'],
                                'semestre' => ['type' => 'STRING'],
                                'credits'  => ['type' => 'STRING'],
                                'ecs'      => [
                                    'type'  => 'ARRAY',
                                    'items' => [
                                        'type'       => 'OBJECT',
                                        'properties' => [
                                            'code'         => ['type' => 'STRING'],
                                            'intitule'     => ['type' => 'STRING'],
                                            'volume_cm'    => ['type' => 'STRING'],
                                            'volume_td'    => ['type' => 'STRING'],
                                            'volume_tp'    => ['type' => 'STRING'],
                                            'volume_td_tp' => ['type' => 'STRING'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'required' => ['ues'],
            ],
            default => throw new \InvalidArgumentException("Type inconnu : {$type}"),
        };
    }

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
            "Au niveau du document, et seulement s'il l'indique : valide_du, la date a partir",
            "de laquelle cet emploi du temps s'applique (« Emploi du temps ... du 15 juin 2026 »,",
            "« a compter du ... »), au format YYYY-MM-DD. Une nouvelle version d'un emploi du",
            "temps « susceptible de modifications » remplace la precedente a cette date.",
            "",
            "REGLES ABSOLUES :",
            "1. N'invente aucune donnee. Un champ absent du document est omis, jamais devine.",
            "2. Une cellule vide n'est pas un creneau : ne la rapporte pas.",
            "3. Si une cellule empile plusieurs informations (filiere, intitule, volume, salle,",
            "   enseignant), separe-les dans les champs correspondants.",
            "4. Si le document ne contient aucun emploi du temps, renvoie un tableau vide.",
            "5. Si le document est un scan sans couche texte, renvoie un tableau vide.",
            "",
            'Reponds UNIQUEMENT avec le JSON, sous la forme {"events": [ ... ], "valide_du": "YYYY-MM-DD"}, valide_du omis si le document ne le dit pas.',
        ]);
    }

    private function getCoursesPrompt(): string
    {
        // Une seule consigne pour tous les fournisseurs : les heures par type.
        return ConsignesMaquette::cours();
    }
}
