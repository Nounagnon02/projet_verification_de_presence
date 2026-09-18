<?php

declare(strict_types=1);

namespace App\ValueObjects;

/**
 * Diagnostic d'une extraction de document : ce que l'analyse a compris, et ce
 * qu'elle n'a pas compris.
 *
 * POURQUOI CE TYPE EXISTE
 *
 * L'extraction d'emploi du temps renvoyait un tableau, et rien d'autre. Un
 * tableau vide couvrait donc quatre situations sans rapport :
 *
 *   - le document ne contient réellement aucun créneau (une note de service) ;
 *   - le document est un scan sans couche texte, donc illisible en l'état ;
 *   - le document contient des créneaux, mais dans une forme que le pipeline
 *     rejette — c'était le cas de TOUS les emplois du temps hebdomadaires, la
 *     forme habituelle à l'UAC : le filtre de sortie écartait en silence tout
 *     créneau sans date calendaire ;
 *   - le modèle a échoué.
 *
 * L'administrateur voyait dans les quatre cas le même écran vide, avec un score
 * de confiance à zéro et un message générique. Rien ne lui disait que son
 * document avait été lu et que son contenu avait été jeté.
 *
 * Distinguer ces états n'est pas cosmétique : le remède diffère radicalement.
 * Un document vide se remplace ; un scan se repasse en OCR ; un contenu détecté
 * mais non interprété se corrige à la main, et surtout signale un défaut du
 * pipeline qu'il faut connaître.
 */
final class DiagnosticExtraction
{
    /** Aucun contenu exploitable dans le document. */
    public const VIDE = 'vide';

    /** Du contenu a été détecté, mais sa structure n'a pas pu être interprétée. */
    public const NON_INTERPRETABLE = 'contenu_non_interpretable';

    /** Une partie des créneaux a été comprise, une autre a été écartée. */
    public const PARTIEL = 'partiellement_interprete';

    /** Tous les créneaux détectés ont été compris et sont cohérents. */
    public const VALIDE = 'valide';

    /** Les créneaux ont été compris mais violent une règle académique. */
    public const INVALIDE = 'invalide';

    /** Les créneaux sont compris mais plusieurs lectures sont possibles. */
    public const AMBIGU = 'ambigu';

    public const TOUS = [
        self::VIDE,
        self::NON_INTERPRETABLE,
        self::PARTIEL,
        self::VALIDE,
        self::INVALIDE,
        self::AMBIGU,
    ];

    /**
     * @param string                     $statut     Une des constantes ci-dessus.
     * @param list<array<string, mixed>> $retenus    Créneaux compris.
     * @param list<array<string, mixed>> $ecartes    Créneaux détectés mais écartés, avec leur motif.
     * @param list<string>               $remarques  Ce que l'analyse veut porter à la connaissance de l'utilisateur.
     * @param array<string, mixed>       $indices    Ce qui a été observé du document : présence de texte, de jours, d'horaires…
     */
    public function __construct(
        public readonly string $statut,
        public readonly array  $retenus = [],
        public readonly array  $ecartes = [],
        public readonly array  $remarques = [],
        public readonly array  $indices = [],
    ) {
        if (!in_array($statut, self::TOUS, true)) {
            throw new \InvalidArgumentException("Statut de diagnostic inconnu : {$statut}");
        }
    }

    public function estExploitable(): bool
    {
        return in_array($this->statut, [self::VALIDE, self::PARTIEL, self::AMBIGU], true);
    }

    /**
     * Message destiné à l'administrateur. Il doit dire ce qui s'est passé ET ce
     * qu'il y a à faire : « aucun créneau » sans explication est ce qui a rendu
     * le défaut d'origine invisible pendant des mois.
     */
    public function message(): string
    {
        $n = count($this->retenus);
        $e = count($this->ecartes);

        return match ($this->statut) {
            self::VIDE => 'Aucun créneau de cours dans ce document. '
                . "S'il s'agit bien d'un emploi du temps, sa structure n'a pas été reconnue : "
                . 'vérifiez que le fichier n\'est pas un scan sans couche texte.',

            self::NON_INTERPRETABLE => "Du contenu a été détecté dans ce document, mais sa structure n'a pas pu "
                . 'être interprétée. Le document n\'est pas vide : il n\'est pas compris. '
                . 'Signalez-le, et importez ces créneaux par fichier CSV en attendant.',

            self::PARTIEL => "{$n} créneau(x) compris, {$e} écarté(s). "
                . 'Les créneaux écartés sont détaillés ci-dessous avec leur motif : '
                . 'corrigez-les à la main ou complétez le document source.',

            self::VALIDE => "{$n} créneau(x) extrait(s) et cohérent(s) avec le modèle académique.",

            self::INVALIDE => "{$n} créneau(x) extrait(s), mais ils violent une ou plusieurs règles "
                . 'académiques. Rien ne sera enregistré avant correction.',

            self::AMBIGU => "{$n} créneau(x) extrait(s), dont la lecture est ambiguë. "
                . 'Vérifiez-les un par un avant de valider.',
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'statut'    => $this->statut,
            'message'   => $this->message(),
            'retenus'   => $this->retenus,
            'ecartes'   => $this->ecartes,
            'remarques' => $this->remarques,
            'indices'   => $this->indices,
        ];
    }
}
