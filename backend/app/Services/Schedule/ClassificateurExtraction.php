<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\ValueObjects\DiagnosticExtraction;
use Illuminate\Support\Facades\Log;

/**
 * Décide de ce qu'il faut conclure d'une extraction : document vide, contenu non
 * compris, extraction partielle, ou extraction exploitable.
 *
 * POURQUOI OBSERVER LE DOCUMENT
 *
 * Un extracteur qui rend un tableau vide ne dit pas POURQUOI. Répondre « aucun
 * créneau » sans avoir regardé le document confond deux situations opposées :
 *
 *   - le document ne contient effectivement aucun emploi du temps ;
 *   - le document en contient un, que le pipeline n'a pas su lire.
 *
 * Dans le premier cas l'utilisateur doit changer de fichier ; dans le second il
 * doit être averti d'un défaut du système, et son fichier ne doit surtout pas
 * être déclaré vide. C'est cette confusion qui a laissé passer, sans que rien ne
 * l'indique, le rejet de TOUS les emplois du temps hebdomadaires.
 *
 * On lit donc le texte du document pour y chercher des marqueurs objectifs
 * d'emploi du temps — noms de jours, plages horaires — indépendamment de ce que
 * le modèle a répondu.
 */
class ClassificateurExtraction
{
    /** Marqueurs de jours, sans accent, tels qu'ils apparaissent dans les fiches. */
    private const JOURS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    public function __construct(
        private readonly NormalisateurCreneau $normalisateur = new NormalisateurCreneau(),
    ) {}

    /**
     * @param  list<array<string, mixed>> $bruts    Créneaux tels que le modèle les a rendus.
     * @param  string|null                $chemin   Chemin du document, pour l'observer.
     */
    public function classer(array $bruts, ?string $chemin = null): DiagnosticExtraction
    {
        $indices = $this->observer($chemin);

        $retenus = [];
        $ecartes = [];

        foreach ($bruts as $i => $brut) {
            if (!is_array($brut)) {
                $ecartes[] = ['rang' => $i + 1, 'motif' => 'Créneau illisible : la réponse du modèle n\'est pas un objet.', 'brut' => $brut];
                continue;
            }

            $r = $this->normalisateur->normaliser($brut);

            if ($r['ok']) {
                $retenus[] = $r['creneau'];
                continue;
            }

            $ecartes[] = [
                'rang'  => $i + 1,
                'champ' => $r['champ'] ?? null,
                'motif' => $r['motif'] ?? 'Créneau non interprétable.',
                'brut'  => $brut,
            ];
        }

        $statut = $this->decider($retenus, $ecartes, $indices);

        $diagnostic = new DiagnosticExtraction(
            statut: $statut,
            retenus: $retenus,
            ecartes: $ecartes,
            remarques: $this->remarques($statut, $indices),
            indices: $indices,
        );

        // Journalisation : sans elle, un diagnostic « non interprétable » reste
        // une impasse pour qui doit corriger le pipeline. On enregistre ce qui a
        // été observé et ce qui a été écarté, jamais le contenu intégral du
        // document.
        Log::info('Extraction emploi du temps classée', [
            'statut'          => $statut,
            'creneaux_bruts'  => count($bruts),
            'retenus'         => count($retenus),
            'ecartes'         => count($ecartes),
            'motifs_ecartes'  => array_values(array_unique(array_column($ecartes, 'champ'))),
            'indices'         => $indices,
        ]);

        return $diagnostic;
    }

    /**
     * Ce que le document lui-même révèle, indépendamment du modèle.
     *
     * @return array<string, mixed>
     */
    private function observer(?string $chemin): array
    {
        $indices = [
            'document_lisible'   => null,
            'longueur_texte'     => 0,
            'jours_detectes'     => [],
            'dates_detectees'    => 0,
            'plages_horaires'    => 0,
            'titre_edt'          => false,
            'entetes_tableau'    => 0,
            'outil_disponible'   => $this->pdftotextDisponible(),
        ];

        if ($chemin === null || !is_file($chemin) || !$indices['outil_disponible']) {
            return $indices;
        }

        $texte = (string) shell_exec('pdftotext -layout ' . escapeshellarg($chemin) . ' - 2>/dev/null');

        $indices['longueur_texte']   = mb_strlen(trim($texte));
        $indices['document_lisible'] = $indices['longueur_texte'] > 50;

        if (!$indices['document_lisible']) {
            return $indices;
        }

        $sansAccent = $this->sansAccent($texte);

        foreach (self::JOURS as $jour) {
            if (str_contains($sansAccent, $jour)) {
                $indices['jours_detectes'][] = $jour;
            }
        }

        // Plages horaires : « 8h – 13h », « 08:00-10:00 », « 14 h 30 ».
        $indices['plages_horaires'] = preg_match_all('/\b\d{1,2}\s*[h:]\s*\d{0,2}\b/u', $texte);

        // Dates calendaires : un planning daté n'affiche AUCUN nom de jour. Ne
        // chercher que « lundi » déclarait donc vide un planning daté dont
        // l'extraction avait échoué — le cas de edt-05 du corpus.
        $indices['dates_detectees'] = preg_match_all(
            '#\b\d{4}-\d{2}-\d{2}\b|\b\d{1,2}/\d{1,2}/\d{2,4}\b#u',
            $texte
        );

        // Le document s'annonce-t-il comme un emploi du temps ? Marqueur faible
        // seul, décisif combiné à une plage horaire.
        foreach (['emploi du temps', 'emploi-du-temps', 'planning', 'edt'] as $marqueur) {
            if (str_contains($sansAccent, $marqueur)) {
                $indices['titre_edt'] = true;
                break;
            }
        }

        // En-têtes de colonnes d'un tableau de planning. C'est ce qui distingue
        // objectivement un emploi du temps minimal — une seule ligne — d'une note
        // administrative qui mentionnerait un jour en passant.
        foreach (['jour', 'horaire', 'salle', 'code ec', 'intitule'] as $entete) {
            if (str_contains($sansAccent, $entete)) {
                $indices['entetes_tableau']++;
            }
        }

        return $indices;
    }

    /**
     * @param list<array<string, mixed>> $retenus
     * @param list<array<string, mixed>> $ecartes
     * @param array<string, mixed>       $indices
     */
    private function decider(array $retenus, array $ecartes, array $indices): string
    {
        if ($retenus !== [] && $ecartes === []) {
            return DiagnosticExtraction::VALIDE;
        }

        if ($retenus !== [] && $ecartes !== []) {
            return DiagnosticExtraction::PARTIEL;
        }

        // Aucun créneau retenu. Le document a-t-il l'air d'un emploi du temps ?
        //
        // Marqueurs indépendants du modèle. Un emploi du temps se reconnaît à un
        // AXE TEMPOREL — noms de jours, ou dates calendaires — CROISÉ avec des
        // plages horaires. Exiger les deux évite de prendre pour un planning une
        // note de service qui mentionne « lundi » une fois.
        //
        // Les deux formes doivent être couvertes : un planning daté n'affiche
        // aucun nom de jour, et ne chercher que ceux-ci le déclarait vide.
        $axeTemporel = max(count($indices['jours_detectes']), $indices['dates_detectees']);

        // UNE plage horaire et UN repère temporel suffisent : un emploi du temps
        // minimal — un seul créneau — n'en porte pas davantage, et le déclarer
        // vide serait faux. Le cas limite est réel : le corpus en contient un.
        //
        // Ce seuil n'expose pas au faux positif de la note administrative : une
        // note ne croise pas un jour avec une plage horaire. Vérifié sur la note
        // de service du corpus, qui mentionne « vendredi » sans aucun horaire.
        $croisement = $axeTemporel >= 1 && $indices['plages_horaires'] >= 1;

        // Un document qui s'annonce comme un emploi du temps et porte des
        // en-têtes de tableau en est un, même si le repère temporel a échappé à
        // la détection.
        $sAnnonce = ($indices['titre_edt'] ?? false) && ($indices['entetes_tableau'] ?? 0) >= 2;

        $ressembleAUnEdt = $croisement || $sAnnonce;

        if ($ecartes !== []) {
            // Le modèle a bien vu quelque chose : ce n'est pas un document vide.
            return DiagnosticExtraction::NON_INTERPRETABLE;
        }

        if ($ressembleAUnEdt) {
            // Le document porte les marqueurs d'un emploi du temps, et le modèle
            // n'en a rien tiré. Le déclarer vide serait un mensonge.
            return DiagnosticExtraction::NON_INTERPRETABLE;
        }

        return DiagnosticExtraction::VIDE;
    }

    /**
     * @param  array<string, mixed> $indices
     * @return list<string>
     */
    private function remarques(string $statut, array $indices): array
    {
        $remarques = [];

        if (!$indices['outil_disponible']) {
            $remarques[] = "L'outil d'analyse de PDF (pdftotext, paquet poppler-utils) n'est pas installé : "
                . 'le système ne peut pas vérifier lui-même si le document contient du texte. '
                . 'Un document déclaré vide ne peut donc pas être confirmé comme tel.';
        } elseif ($indices['document_lisible'] === false) {
            $remarques[] = 'Ce document ne contient aucune couche texte : c\'est une image, '
                . 'généralement un scan. Fournissez un PDF natif, ou passez-le par un outil de '
                . 'reconnaissance de caractères.';
        }

        $axe = max(count($indices['jours_detectes']), $indices['dates_detectees'] ?? 0);

        if ($statut === DiagnosticExtraction::NON_INTERPRETABLE && $axe > 0) {
            $quoi = $indices['jours_detectes'] !== []
                ? count($indices['jours_detectes']) . ' jour(s) de la semaine'
                : ($indices['dates_detectees'] ?? 0) . ' date(s) calendaire(s)';

            $remarques[] = "Le document mentionne {$quoi} et " . $indices['plages_horaires']
                . " plage(s) horaire(s) : il contient bien un emploi du temps, dont la structure "
                . "n'a pas été comprise. Ce n'est PAS un document vide.";
        }

        return $remarques;
    }

    private function pdftotextDisponible(): bool
    {
        return !empty(shell_exec('command -v pdftotext 2>/dev/null'));
    }

    private function sansAccent(string $valeur): string
    {
        return strtr(mb_strtolower($valeur), [
            'à' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c',
        ]);
    }
}
