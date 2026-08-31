<?php

/**
 * Harnais d'évaluation du module IA (§4.5 du mémoire, cas IA-01 à IA-07).
 *
 * CE QU'IL MESURE
 *   TEC — taux d'extraction correcte : proportion des champs de la vérité
 *         terrain retrouvés à l'identique dans la sortie du modèle.
 *   TFP — taux de faux positifs : proportion des éléments produits par le modèle
 *         qui n'existent pas dans la vérité terrain. C'est la métrique qui
 *         compte le plus : une extraction qui invente un créneau crée un cours
 *         fantôme, et donc des absences pour des étudiants réels.
 *   SCM — score de confiance moyen rapporté par le provider.
 *
 * DEUX MODES, DÉLIBÉRÉMENT
 *   --reel    appelle réellement le provider. Consomme du quota, non
 *             déterministe. C'est le mode de la campagne de mesure.
 *   (défaut)  rejoue les réponses enregistrées dans enregistrements/.
 *             Déterministe et gratuit : utilisable en intégration continue.
 *
 * Sans cette séparation, ou bien la CI brûle du quota à chaque exécution, ou
 * bien la mesure n'est jamais refaite. Les deux arrivent en pratique.
 *
 * Usage :
 *   cd backend
 *   php ../tests/ia/evaluer.php --reel --provider=gemini
 *   php ../tests/ia/evaluer.php --reel --provider=groq --seulement=edt-01-simple
 *   php ../tests/ia/evaluer.php
 */

require __DIR__ . '/../../backend/vendor/autoload.php';
$app = require __DIR__ . '/../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\ValueObjects\AnalysisResult;

$options    = getopt('', ['reel', 'provider::', 'seulement::']);
$reel       = isset($options['reel']);
$provider   = $options['provider'] ?? config('ai.default');
$seulement  = $options['seulement'] ?? null;

$base       = __DIR__;
$dossierEnr = $base . '/enregistrements/' . $provider;
if (!is_dir($dossierEnr)) {
    mkdir($dossierEnr, 0775, true);
}

$journal = fn (string $m) => fwrite(STDERR, $m . PHP_EOL);

$index = json_decode(@file_get_contents($base . '/index.json'), true);
if (!$index) {
    $journal("index.json introuvable. Lancer generer-corpus.php d'abord.");
    exit(1);
}

if ($reel && empty(config("ai.providers.{$provider}.api_key"))) {
    $journal("Aucune clé API pour « {$provider} ». Renseigner "
        . strtoupper($provider) . "_API_KEY dans .env.");
    exit(1);
}

/** La casse, les accents et les espaces ne décident pas si une extraction est correcte. */
function normaliser(mixed $v): string
{
    $s = is_scalar($v) ? (string) $v : json_encode($v);
    $s = mb_strtolower((string) $s, 'UTF-8');
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;

    return preg_replace('/[^a-z0-9]/', '', $s);
}

/**
 * Compare une liste extraite à la vérité terrain.
 *
 * L'appariement se fait sur une clé d'identité, pas sur la position : comparer
 * rang par rang punirait un simple changement d'ordre, qui n'est pas une erreur
 * d'extraction.
 *
 * @return array{0:int,1:int,2:int,3:int} [champs corrects, champs attendus, inventés, produits]
 */
/**
 * Repère temporel canonique d'un créneau, quelle que soit la forme.
 *
 * Le harnais comparait le champ « date » des deux côtés. Depuis que le pipeline
 * accepte les emplois du temps HEBDOMADAIRES, un créneau porte « jour_semaine »
 * (1 = lundi … 7 = dimanche) et aucune date : la comparaison ne trouvait plus
 * rien et rapportait 0 % d'extraction avec 100 % de faux positifs, sur des
 * extractions pourtant exactes.
 *
 * Mesurer contre une forme que le contrat n'a plus fait passer une extraction
 * correcte pour un échec total. On ramène donc les deux côtés au même repère.
 */
function repereTemporel(array $creneau): string
{
    static $jours = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi',
                     5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];

    if (!empty($creneau['date'])) {
        return normaliser((string) $creneau['date']);
    }

    if (!empty($creneau['jour_semaine']) && isset($jours[(int) $creneau['jour_semaine']])) {
        return $jours[(int) $creneau['jour_semaine']];
    }

    return normaliser((string) ($creneau['jour'] ?? ''));
}

/** Désignation de l'EC, quel que soit le champ qui la porte. */
function designationEc(array $creneau): string
{
    return normaliser(trim(implode(' ', array_filter([
        $creneau['ec'] ?? null,
        $creneau['ec_code'] ?? null,
        $creneau['ec_libelle'] ?? null,
    ]))));
}

function comparerListes(array $extraits, array $attendus, array $clesIdentite, array $clesComparees): array
{
    $indexer = function (array $liste) use ($clesIdentite) {
        $sortie = [];
        foreach ($liste as $item) {
            if (!is_array($item)) {
                continue;
            }
            $identite = implode('|', array_map(fn ($c) => normaliser($item[$c] ?? ''), $clesIdentite));
            if (trim($identite, '|') !== '') {
                $sortie[$identite] = $item;
            }
        }

        return $sortie;
    };

    $iExtraits = $indexer($extraits);
    $iAttendus = $indexer($attendus);

    $ok = 0;
    $total = 0;

    foreach ($iAttendus as $identite => $attendu) {
        foreach ($clesComparees as $cle) {
            if (!array_key_exists($cle, $attendu)) {
                continue;
            }
            $total++;
            $extrait = $iExtraits[$identite] ?? null;
            if ($extrait !== null && normaliser($extrait[$cle] ?? '') === normaliser($attendu[$cle])) {
                $ok++;
            }
        }
    }

    return [$ok, $total, count(array_diff_key($iExtraits, $iAttendus)), count($iExtraits)];
}

/**
 * Récupère la liste plausible dans une structure de sortie non garantie.
 *
 * Les providers ne promettent pas un nommage exact. Sans cette tolérance, le
 * harnais mesurerait la conformité du nommage plutôt que la qualité de
 * l'extraction.
 */
function extraireListe(?array $donnees, array $clesPossibles): array
{
    if (!$donnees) {
        return [];
    }
    foreach ($clesPossibles as $cle) {
        if (isset($donnees[$cle]) && is_array($donnees[$cle])) {
            return $donnees[$cle];
        }
    }
    foreach ($donnees as $valeur) {
        if (is_array($valeur) && $valeur !== [] && is_array(reset($valeur))) {
            return $valeur;
        }
    }

    return [];
}

$resultats = [];

foreach ($index['liste'] as $doc) {
    if ($seulement !== null && $doc['id'] !== $seulement) {
        continue;
    }

    $verite    = json_decode(file_get_contents($base . '/' . $doc['verite']), true);
    $cheminEnr = $dossierEnr . '/' . $doc['id'] . '.json';

    if ($reel) {
        $journal("→ {$doc['id']} ({$provider})…");
        $debut  = microtime(true);

        // Les providers recoivent leur cle par constructeur : app($classe)
        // injecterait null et l'analyse echouerait sur « cle manquante », ce qui
        // ressemblerait a un mauvais resultat du modele. On reproduit donc la
        // liaison de AppServiceProvider.
        $classes = [
            'gemini'     => \App\Services\Providers\GeminiProvider::class,
            'groq'       => \App\Services\Providers\GroqProvider::class,
            'openrouter' => \App\Services\Providers\OpenRouterProvider::class,
        ];

        try {
            if (!isset($classes[$provider])) {
                throw new \InvalidArgumentException("Provider inconnu : {$provider}");
            }
            /** @var \App\Contracts\AiProviderInterface $p */
            $p = new $classes[$provider](config("ai.providers.{$provider}.api_key"));
            $r = $p->analyzeDocument($base . '/' . $doc['pdf'], $doc['type']);
        } catch (\Throwable $e) {
            $r = AnalysisResult::failed(get_class($e) . ' : ' . $e->getMessage());
        }

        $brut = [
            'status'        => $r->status,
            'data'          => $r->data,
            'confidence'    => $r->confidence,
            'warning'       => $r->warning,
            'error_message' => $r->errorMessage,
            'duree_ms'      => (int) ((microtime(true) - $debut) * 1000),
        ];
        file_put_contents($cheminEnr, json_encode($brut, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } else {
        if (!file_exists($cheminEnr)) {
            $journal("  {$doc['id']} : aucun enregistrement pour « {$provider} », ignoré.");
            continue;
        }
        $brut = json_decode(file_get_contents($cheminEnr), true);
    }

    $attendu = $verite['attendu'];

    // Document non pertinent : la bonne réponse est un refus ou un vide.
    if (!empty($attendu['refus_ou_vide'])) {
        $liste   = extraireListe($brut['data'] ?? null, ['events', 'creneaux', 'ues', 'schedule']);
        $correct = ($brut['status'] ?? '') === 'failed' || count($liste) === 0;

        $resultats[] = [
            'id' => $doc['id'], 'type' => $doc['type'], 'difficulte' => $doc['difficulte'],
            'tec' => $correct ? 1.0 : 0.0,
            'tfp' => $correct ? 0.0 : 1.0,
            'confiance' => $brut['confidence'] ?? null,
            'statut' => $brut['status'] ?? 'inconnu',
            'detail' => $correct ? 'refus correct' : 'a inventé ' . count($liste) . ' élément(s)',
            'duree_ms' => $brut['duree_ms'] ?? null,
        ];
        continue;
    }

    if ($doc['type'] === 'schedule') {
        // Comparaison sur le schema REELLEMENT produit par les providers —
        // { ec, date, heure_debut, heure_fin, salle } — et non sur un schema
        // suppose. Mesurer contre un nommage invente ferait passer une
        // extraction correcte pour un echec total : c'est arrive.
        //
        // Le champ « ec » concatene volontiers le code et l'intitule
        // (« GLT501 - Algorithmique avancee ») : on verifie donc que le code
        // attendu y FIGURE, plutot qu'une egalite stricte.
        $extraits = extraireListe($brut['data'] ?? null, ['events', 'creneaux', 'schedule', 'seances']);

        // Les deux cotes sont ramenes au meme repere temporel : une date
        // calendaire pour un planning date, un jour de semaine pour un emploi du
        // temps hebdomadaire.
        $attendusNormalises = array_map(fn ($c) => [
            'repere'      => repereTemporel($c),
            'heure_debut' => $c['heure_debut'],
            'heure_fin'   => $c['heure_fin'],
            'salle'       => $c['salle'],
            'ec'          => $c['ec_code'],
        ], $attendu['creneaux']);

        $extraitsNormalises = array_map(fn ($e) => is_array($e) ? [
            'repere'      => repereTemporel($e),
            'heure_debut' => $e['heure_debut'] ?? '',
            'heure_fin'   => $e['heure_fin'] ?? '',
            'salle'       => $e['salle'] ?? '',
            'ec'          => designationEc($e),
        ] : [], $extraits);

        [$ok, $total, $inventes, $produits] = comparerListes(
            $extraitsNormalises,
            $attendusNormalises,
            ['repere', 'heure_debut'],
            ['repere', 'heure_debut', 'heure_fin', 'salle'],
        );

        // Le code d'EC, compte a part car sa comparaison est une inclusion.
        $parIdentite = [];
        foreach ($extraitsNormalises as $e) {
            if ($e === []) {
                continue;
            }
            $parIdentite[$e['repere'] . '|' . normaliser($e['heure_debut'])] = $e;
        }
        foreach ($attendusNormalises as $a) {
            $total++;
            $cle = $a['repere'] . '|' . normaliser($a['heure_debut']);
            $trouve = $parIdentite[$cle] ?? null;
            if ($trouve !== null && str_contains($trouve['ec'], normaliser($a['ec']))) {
                $ok++;
            }
        }
    } else {
        $ecsAttendus = [];
        foreach ($attendu['ues'] as $ue) {
            foreach ($ue['ecs'] as $ec) {
                $ecsAttendus[] = $ec;
            }
        }

        // Une liste d'UE porte ses EC en profondeur : on aplatit avant de comparer.
        $plats = [];
        foreach (extraireListe($brut['data'] ?? null, ['ecs', 'ues', 'courses']) as $item) {
            if (isset($item['ecs']) && is_array($item['ecs'])) {
                foreach ($item['ecs'] as $ec) {
                    $plats[] = $ec;
                }
            } else {
                $plats[] = $item;
            }
        }

        [$ok, $total, $inventes, $produits] = comparerListes(
            $plats,
            $ecsAttendus,
            ['code'],
            ['code', 'intitule', 'volume_horaire'],
        );
    }

    $resultats[] = [
        'id' => $doc['id'], 'type' => $doc['type'], 'difficulte' => $doc['difficulte'],
        'tec' => $total > 0 ? round($ok / $total, 4) : 0.0,
        'tfp' => $produits > 0 ? round($inventes / $produits, 4) : 0.0,
        'confiance' => $brut['confidence'] ?? null,
        'statut' => $brut['status'] ?? 'inconnu',
        'detail' => "{$ok}/{$total} champs, {$inventes} invention(s) sur {$produits} produit(s)",
        'duree_ms' => $brut['duree_ms'] ?? null,
    ];
}

if ($resultats === []) {
    $journal("Aucun résultat. En mode rejeu, produire d'abord des enregistrements avec --reel.");
    exit(1);
}

$moy  = fn (array $a) => $a === [] ? null : round(array_sum($a) / count($a), 4);
$conf = array_values(array_filter(array_column($resultats, 'confiance'), fn ($c) => $c !== null));

$synthese = [
    'provider'       => $provider,
    'mode'           => $reel ? 'reel' : 'rejeu',
    'documents'      => count($resultats),
    'tec_moyen'      => $moy(array_column($resultats, 'tec')),
    'tfp_moyen'      => $moy(array_column($resultats, 'tfp')),
    'scm'            => $moy($conf),
    'echecs_analyse' => count(array_filter($resultats, fn ($r) => $r['statut'] === 'failed')),
    'par_document'   => $resultats,
];

printf("\n%s\n", str_repeat('=', 72));
printf(" Évaluation du module IA — provider « %s » (mode %s)\n", $provider, $synthese['mode']);
printf("%s\n\n", str_repeat('=', 72));
printf(" %-28s %7s %7s %7s  %s\n", 'Document', 'TEC', 'TFP', 'Conf.', 'Détail');
foreach ($resultats as $r) {
    printf(
        " %-28s %6.1f%% %6.1f%% %7s  %s\n",
        $r['id'], $r['tec'] * 100, $r['tfp'] * 100,
        $r['confiance'] !== null ? number_format($r['confiance'], 2) : '—',
        $r['detail'],
    );
}
printf("\n %-28s %6.1f%% %6.1f%% %7s\n", 'MOYENNE',
    ($synthese['tec_moyen'] ?? 0) * 100,
    ($synthese['tfp_moyen'] ?? 0) * 100,
    $synthese['scm'] !== null ? number_format($synthese['scm'], 2) : '—');
printf(" Analyses en échec : %d / %d\n", $synthese['echecs_analyse'], $synthese['documents']);
printf("%s\n", str_repeat('=', 72));

file_put_contents(
    $base . '/resultats-' . $provider . '.json',
    json_encode($synthese, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
);
$journal('Résultat écrit dans resultats-' . $provider . '.json');
