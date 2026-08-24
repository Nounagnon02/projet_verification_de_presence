<?php

/**
 * Génère le corpus d'évaluation du module IA (§4.5 du mémoire).
 *
 * POURQUOI CE SCRIPT EXISTE
 * ─────────────────────────
 * Les 45 documents sur lesquels le mémoire annonce 89,1 % d'extraction correcte
 * ne sont pas dans le dépôt. Ce chiffre n'est donc PAS reproductible, et aucun
 * outillage n'y changera quoi que ce soit : on ne peut pas re-mesurer ce qu'on
 * n'a plus.
 *
 * Ce que ce script permet, en revanche : produire un corpus dont la vérité
 * terrain est connue par construction, et donc obtenir des chiffres RÉELS et
 * reproductibles. Le mémoire pourra publier ceux-là, en disant combien de
 * documents ils couvrent.
 *
 * Chaque document est généré avec sa vérité terrain en JSON. Les variations sont
 * délibérées et couvrent ce qui met un extracteur en difficulté :
 *   - mise en page tabulaire nette (cas facile)
 *   - libellés abrégés, accents, casse irrégulière
 *   - créneaux à cheval sur la pause, séances doubles
 *   - lignes vides et colonnes décalées
 *   - document non pertinent (doit être refusé, pas extrait)
 *
 * Usage :
 *   cd backend && php ../tests/ia/generer-corpus.php
 */

require __DIR__ . '/../../backend/vendor/autoload.php';
$app = require __DIR__ . '/../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Barryvdh\DomPDF\Facade\Pdf;

$dossierCorpus = __DIR__ . '/corpus';
$dossierVerites = __DIR__ . '/verites';

foreach ([$dossierCorpus, $dossierVerites] as $d) {
    if (!is_dir($d)) {
        mkdir($d, 0775, true);
    }
}

$journal = fn (string $m) => fwrite(STDERR, $m . PHP_EOL);

/**
 * Emploi du temps : rendu tabulaire classique.
 */
function documentEmploiDuTemps(array $spec): string
{
    $lignes = '';
    foreach ($spec['creneaux'] as $c) {
        $lignes .= sprintf(
            '<tr><td>%s</td><td>%s - %s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            htmlspecialchars($c['jour']),
            htmlspecialchars($c['heure_debut']),
            htmlspecialchars($c['heure_fin']),
            htmlspecialchars($c['ec_code']),
            htmlspecialchars($c['ec_intitule']),
            htmlspecialchars($c['salle']),
        );
    }

    return <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px}
h1{font-size:15px;text-align:center;margin-bottom:2px}
.st{text-align:center;font-size:10px;color:#444;margin-bottom:14px}
table{width:100%;border-collapse:collapse;font-size:10px}
th,td{border:1px solid #444;padding:4px 6px}
th{background:#1E40AF;color:#fff}
</style></head><body>
<h1>UNIVERSITÉ D'ABOMEY-CALAVI</h1>
<p class="st">Emploi du temps — {$spec['filiere']} {$spec['niveau']} — Semestre {$spec['semestre']} — {$spec['annee']}</p>
<table><thead><tr><th>Jour</th><th>Horaire</th><th>Code EC</th><th>Intitulé</th><th>Salle</th></tr></thead>
<tbody>{$lignes}</tbody></table>
</body></html>
HTML;
}

/**
 * Maquette pédagogique : hiérarchie UE puis EC.
 */
function documentMaquette(array $spec): string
{
    $lignes = '';
    foreach ($spec['ues'] as $ue) {
        $lignes .= sprintf(
            '<tr class="ue"><td colspan="2"><b>%s — %s</b></td><td>%s h</td></tr>',
            htmlspecialchars($ue['code']),
            htmlspecialchars($ue['intitule']),
            $ue['volume_horaire'],
        );
        foreach ($ue['ecs'] as $ec) {
            $lignes .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s h</td></tr>',
                htmlspecialchars($ec['code']),
                htmlspecialchars($ec['intitule']),
                $ec['volume_horaire'],
            );
        }
    }

    return <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px}
h1{font-size:15px;text-align:center;margin-bottom:2px}
.st{text-align:center;font-size:10px;color:#444;margin-bottom:14px}
table{width:100%;border-collapse:collapse;font-size:10px}
th,td{border:1px solid #444;padding:4px 6px}
th{background:#1E40AF;color:#fff}
tr.ue td{background:#e5edff}
</style></head><body>
<h1>UNIVERSITÉ D'ABOMEY-CALAVI</h1>
<p class="st">Maquette pédagogique — {$spec['filiere']} {$spec['niveau']} — Semestre {$spec['semestre']} — {$spec['annee']}</p>
<table><thead><tr><th>Code</th><th>Intitulé</th><th>Volume</th></tr></thead>
<tbody>{$lignes}</tbody></table>
</body></html>
HTML;
}

/**
 * Document non pertinent : l'extracteur doit refuser, pas inventer.
 */
function documentHorsSujet(): string
{
    return <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px;line-height:1.5}
h1{font-size:15px}
</style></head><body>
<h1>Note de service n° 2026-114</h1>
<p>Objet : fermeture exceptionnelle du service de la scolarité.</p>
<p>Le service de la scolarité sera fermé au public le vendredi suivant, pour
cause d'inventaire annuel des archives. Les demandes d'attestation déposées
avant cette date seront traitées dans l'ordre d'arrivée.</p>
<p>Les personnels concernés sont invités à prendre contact avec le secrétariat
général pour toute question relative à l'organisation de cette journée.</p>
<p>Le Secrétaire Général</p>
</body></html>
HTML;
}

// ─── Définition du corpus ─────────────────────────────────────────────────────
// Chaque entrée : identifiant, type, difficulté, contenu et vérité terrain.

$corpus = [];

// --- Emplois du temps ---
$corpus[] = [
    'id' => 'edt-01-simple',
    'type' => 'schedule',
    'difficulte' => 'facile',
    'note' => 'Tableau net, codes explicites, horaires réguliers.',
    'spec' => [
        'filiere' => 'GLT', 'niveau' => 'L3', 'semestre' => 5, 'annee' => '2025-2026',
        'creneaux' => [
            ['jour' => 'Lundi', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'ec_code' => 'GLT501', 'ec_intitule' => 'Algorithmique avancée', 'salle' => 'A-101'],
            ['jour' => 'Lundi', 'heure_debut' => '10:15', 'heure_fin' => '12:15', 'ec_code' => 'GLT502', 'ec_intitule' => 'Bases de données', 'salle' => 'A-101'],
            ['jour' => 'Mardi', 'heure_debut' => '14:00', 'heure_fin' => '16:00', 'ec_code' => 'GLT503', 'ec_intitule' => 'Réseaux informatiques', 'salle' => 'B-204'],
            ['jour' => 'Mercredi', 'heure_debut' => '08:00', 'heure_fin' => '11:00', 'ec_code' => 'GLT504', 'ec_intitule' => 'Génie logiciel', 'salle' => 'Amphi C'],
        ],
    ],
];

$corpus[] = [
    'id' => 'edt-02-abreviations',
    'type' => 'schedule',
    'difficulte' => 'moyenne',
    'note' => 'Libellés abrégés, casse irrégulière, accents inégaux.',
    'spec' => [
        'filiere' => 'IM', 'niveau' => 'M1', 'semestre' => 1, 'annee' => '2025-2026',
        'creneaux' => [
            ['jour' => 'LUNDI', 'heure_debut' => '07:30', 'heure_fin' => '09:30', 'ec_code' => 'IM101', 'ec_intitule' => 'Stat. appliq.', 'salle' => 'S-12'],
            ['jour' => 'mardi', 'heure_debut' => '09:45', 'heure_fin' => '11:45', 'ec_code' => 'IM102', 'ec_intitule' => 'Econometrie', 'salle' => 'S-12'],
            ['jour' => 'Jeudi', 'heure_debut' => '15:00', 'heure_fin' => '18:00', 'ec_code' => 'IM103', 'ec_intitule' => 'Rech. Opérationnelle', 'salle' => 'S-14'],
        ],
    ],
];

$corpus[] = [
    'id' => 'edt-03-seances-longues',
    'type' => 'schedule',
    'difficulte' => 'moyenne',
    'note' => 'Séances de 4 h à cheval sur la pause déjeuner.',
    'spec' => [
        'filiere' => 'SEG', 'niveau' => 'L2', 'semestre' => 3, 'annee' => '2025-2026',
        'creneaux' => [
            ['jour' => 'Lundi', 'heure_debut' => '10:00', 'heure_fin' => '14:00', 'ec_code' => 'SEG301', 'ec_intitule' => 'Comptabilité générale', 'salle' => 'D-01'],
            ['jour' => 'Vendredi', 'heure_debut' => '12:00', 'heure_fin' => '16:00', 'ec_code' => 'SEG302', 'ec_intitule' => 'Droit des affaires', 'salle' => 'D-02'],
        ],
    ],
];

$corpus[] = [
    'id' => 'edt-04-creneau-unique',
    'type' => 'schedule',
    'difficulte' => 'facile',
    'note' => 'Un seul créneau : cas limite du document minimal.',
    'spec' => [
        'filiere' => 'STAPS', 'niveau' => 'L1', 'semestre' => 1, 'annee' => '2025-2026',
        'creneaux' => [
            ['jour' => 'Samedi', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'ec_code' => 'STA101', 'ec_intitule' => 'Anatomie fonctionnelle', 'salle' => 'Gymnase'],
        ],
    ],
];

// --- Maquettes pédagogiques ---
$corpus[] = [
    'id' => 'maq-01-simple',
    'type' => 'courses',
    'difficulte' => 'facile',
    'note' => 'Deux UE, quatre EC, volumes cohérents.',
    'spec' => [
        'filiere' => 'GLT', 'niveau' => 'L3', 'semestre' => 5, 'annee' => '2025-2026',
        'ues' => [
            ['code' => 'UE-GLT51', 'intitule' => 'Développement logiciel', 'volume_horaire' => 60, 'ecs' => [
                ['code' => 'GLT501', 'intitule' => 'Algorithmique avancée', 'volume_horaire' => 30],
                ['code' => 'GLT502', 'intitule' => 'Bases de données', 'volume_horaire' => 30],
            ]],
            ['code' => 'UE-GLT52', 'intitule' => 'Systèmes et réseaux', 'volume_horaire' => 50, 'ecs' => [
                ['code' => 'GLT503', 'intitule' => 'Réseaux informatiques', 'volume_horaire' => 25],
                ['code' => 'GLT504', 'intitule' => 'Génie logiciel', 'volume_horaire' => 25],
            ]],
        ],
    ],
];

$corpus[] = [
    'id' => 'maq-02-volumes-incoherents',
    'type' => 'courses',
    'difficulte' => 'difficile',
    'note' => "Le volume de l'UE ne correspond pas à la somme de ses EC : l'extracteur doit rapporter ce qu'il lit, pas corriger.",
    'spec' => [
        'filiere' => 'IM', 'niveau' => 'M1', 'semestre' => 1, 'annee' => '2025-2026',
        'ues' => [
            ['code' => 'UE-IM11', 'intitule' => 'Méthodes quantitatives', 'volume_horaire' => 100, 'ecs' => [
                ['code' => 'IM101', 'intitule' => 'Statistiques appliquées', 'volume_horaire' => 30],
                ['code' => 'IM102', 'intitule' => 'Économétrie', 'volume_horaire' => 30],
            ]],
        ],
    ],
];

$corpus[] = [
    'id' => 'maq-03-nombreux-ec',
    'type' => 'courses',
    'difficulte' => 'moyenne',
    'note' => 'Une UE portant six EC : teste la complétude de l\'extraction.',
    'spec' => [
        'filiere' => 'SEG', 'niveau' => 'L2', 'semestre' => 3, 'annee' => '2025-2026',
        'ues' => [
            ['code' => 'UE-SEG31', 'intitule' => 'Fondamentaux de gestion', 'volume_horaire' => 120, 'ecs' => [
                ['code' => 'SEG301', 'intitule' => 'Comptabilité générale', 'volume_horaire' => 20],
                ['code' => 'SEG302', 'intitule' => 'Droit des affaires', 'volume_horaire' => 20],
                ['code' => 'SEG303', 'intitule' => 'Microéconomie', 'volume_horaire' => 20],
                ['code' => 'SEG304', 'intitule' => 'Macroéconomie', 'volume_horaire' => 20],
                ['code' => 'SEG305', 'intitule' => 'Mathématiques financières', 'volume_horaire' => 20],
                ['code' => 'SEG306', 'intitule' => 'Anglais des affaires', 'volume_horaire' => 20],
            ]],
        ],
    ],
];

// --- Document non pertinent ---
$corpus[] = [
    'id' => 'hors-01-note-de-service',
    'type' => 'schedule',
    'difficulte' => 'refus attendu',
    'note' => "Note administrative sans aucun créneau. L'extracteur doit refuser ou renvoyer un ensemble vide, jamais inventer.",
    'spec' => ['hors_sujet' => true],
];

// ─── Génération ───────────────────────────────────────────────────────────────
$index = [];

foreach ($corpus as $doc) {
    $html = isset($doc['spec']['hors_sujet'])
        ? documentHorsSujet()
        : ($doc['type'] === 'schedule' ? documentEmploiDuTemps($doc['spec']) : documentMaquette($doc['spec']));

    $cheminPdf = $dossierCorpus . '/' . $doc['id'] . '.pdf';
    Pdf::loadHTML($html)->save($cheminPdf);

    // Vérité terrain : ce que l'extraction DOIT produire.
    $verite = [
        'id'         => $doc['id'],
        'type'       => $doc['type'],
        'difficulte' => $doc['difficulte'],
        'note'       => $doc['note'],
    ];

    if (isset($doc['spec']['hors_sujet'])) {
        $verite['attendu'] = ['refus_ou_vide' => true];
    } elseif ($doc['type'] === 'schedule') {
        $verite['attendu'] = [
            'filiere'  => $doc['spec']['filiere'],
            'niveau'   => $doc['spec']['niveau'],
            'semestre' => $doc['spec']['semestre'],
            'creneaux' => $doc['spec']['creneaux'],
        ];
    } else {
        $verite['attendu'] = [
            'filiere'  => $doc['spec']['filiere'],
            'niveau'   => $doc['spec']['niveau'],
            'semestre' => $doc['spec']['semestre'],
            'ues'      => $doc['spec']['ues'],
        ];
    }

    file_put_contents(
        $dossierVerites . '/' . $doc['id'] . '.json',
        json_encode($verite, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    );

    $index[] = [
        'id'         => $doc['id'],
        'type'       => $doc['type'],
        'difficulte' => $doc['difficulte'],
        'pdf'        => 'corpus/' . $doc['id'] . '.pdf',
        'verite'     => 'verites/' . $doc['id'] . '.json',
        'octets'     => filesize($cheminPdf),
    ];

    $journal(sprintf('  %-28s %-9s %7d o', $doc['id'], $doc['type'], filesize($cheminPdf)));
}

file_put_contents(
    __DIR__ . '/index.json',
    json_encode([
        'genere_le' => now()->toIso8601String(),
        'documents' => count($index),
        'liste'     => $index,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
);

$journal('');
$journal(count($index) . ' documents generes, verites terrain incluses.');
