<?php

namespace App\Services\Schedule;

use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Filiere;
use App\Models\Salle;
use App\Services\SemesterService;
use Illuminate\Support\Collection;

/**
 * Valide un créneau d'emploi du temps contre le modèle académique, et détecte
 * les conflits.
 *
 * POURQUOI CE SERVICE EXISTE
 *
 * Le projet portait DEUX chemins d'import d'emploi du temps, d'exigence
 * incomparable :
 *
 *  - CsvImportController::importSchedule résolvait filière, année, UE, EC et
 *    salle, validait jour et heures, détectait les conflits de salle et d'EC,
 *    puis créait des créneaux hebdomadaires. Rigoureux.
 *
 *  - ImportController::validateEvents se contentait de « exists:ecs,id » et
 *    créait des événements datés. Aucune vérification de cohérence : un EC
 *    d'une autre filière, un semestre étranger au niveau, une salle inconnue
 *    passaient sans un mot. Aucune détection de conflit — l'écran en signalait
 *    côté client, le serveur acceptait tout.
 *
 * Deux chemins vers la même table, l'un sûr, l'autre non : la fiabilité du
 * système valait celle du plus faible. Ce service porte les règles une seule
 * fois, et les deux chemins s'y adossent.
 *
 * PRÉCHARGEMENT
 *
 * Toutes les données de référence sont chargées UNE fois, avant la boucle de
 * validation. L'ancien code interrogeait la base pour chaque ligne — filière,
 * année, UE, EC, salle, puis deux requêtes de conflit : sept requêtes par
 * créneau, soit plusieurs centaines pour un emploi du temps de faculté, sur une
 * base hébergée dont chaque aller-retour coûte.
 */
class ValidateurCreneau
{
    public const VALIDE      = 'valide';
    public const INVALIDE    = 'invalide';
    public const AMBIGU      = 'ambigu';
    public const INTROUVABLE = 'introuvable';
    public const CONFLIT     = 'conflit';
    public const DOUBLON     = 'doublon';

    /** @var Collection<int, Ec> */
    private Collection $ecs;

    /** @var Collection<int, Salle> */
    private Collection $salles;

    /**
     * Créneaux déjà en base, préchargés UNE fois.
     *
     * Chaque créneau du lot déclenchait auparavant trois requêtes — doublon,
     * conflit de salle, conflit de promotion. Mesuré à 3,08 requêtes par créneau
     * sur un lot de 48, soit 148 allers-retours. Sur une base hébergée à ~20 ms
     * de latence, un emploi du temps de faculté y passait plusieurs secondes,
     * pour des données tenant en mémoire.
     *
     * @var Collection<int, EmploiDuTemps>
     */
    private Collection $existants;

    private ?Filiere $filiere = null;

    /**
     * Créneaux déjà validés dans le lot courant, pour détecter les conflits
     * INTERNES au fichier. Sans cela, un document se contredisant lui-même
     * passait : les conflits n'étaient cherchés que contre la base.
     *
     * @var list<array<string, mixed>>
     */
    private array $lot = [];

    public function __construct(
        private readonly SemesterService $semestres = new SemesterService(),
    ) {}

    /**
     * Prépare la validation pour une filière et une année données.
     *
     * @return array{ok: bool, motif?: string}
     */
    public function preparer(int $filiereId, int $anneeId): array
    {
        $this->filiere = Filiere::find($filiereId);
        $this->lot = [];

        if ($this->filiere === null) {
            return ['ok' => false, 'motif' => "Filière {$filiereId} introuvable."];
        }

        // Les ECs de CETTE filière et de CETTE année, et eux seuls. Un EC existant
        // ailleurs n'est pas un EC valide ici : c'est la vérification qui manquait,
        // « exists:ecs,id » acceptant n'importe quel identifiant du système.
        $this->ecs = Ec::query()
            ->with('ue')
            ->whereHas('ue', fn ($q) => $q->where('filiere_id', $filiereId)->where('annee_id', $anneeId))
            ->get();

        $this->salles = Salle::query()->get();

        // Tous les créneaux de l'emploi du temps, chargés une fois. Le volume est
        // borné par nature : un établissement ne compte que quelques centaines de
        // créneaux hebdomadaires.
        $this->existants = EmploiDuTemps::query()
            ->select(['id', 'ec_id', 'filiere_id', 'jour_semaine', 'heure_debut', 'heure_fin', 'salle_id'])
            ->get();

        return ['ok' => true];
    }

    /**
     * Valide un créneau normalisé.
     *
     * @param  array<string, mixed> $creneau  Sortie de NormalisateurCreneau.
     * @return array{statut: string, creneau?: array<string, mixed>, motifs: list<string>}
     */
    public function valider(array $creneau): array
    {
        if ($this->filiere === null) {
            throw new \LogicException('preparer() doit être appelé avant valider().');
        }

        $motifs = [];

        // ── 1. L'EC existe-t-il DANS cette filière et cette année ? ──────
        $ec = $this->resoudreEc($creneau);

        if ($ec === null) {
            $designation = $creneau['ec_code'] ?? $creneau['ec_libelle'] ?? '?';

            return [
                'statut' => self::INTROUVABLE,
                'motifs' => [
                    "L'enseignement « {$designation} » n'existe pas dans la filière "
                    . "{$this->filiere->code} pour cette année académique. "
                    . "Créez-le d'abord, ou corrigez la filière de destination.",
                ],
            ];
        }

        if (is_array($ec)) {
            // Plusieurs ECs portent ce libellé : la lecture est ambiguë et un
            // choix arbitraire rattacherait le créneau au mauvais cours.
            $codes = implode(', ', array_map(fn ($e) => $e->code, $ec));

            return [
                'statut' => self::AMBIGU,
                'motifs' => [
                    "« {$creneau['ec_libelle']} » correspond à plusieurs enseignements "
                    . "({$codes}). Précisez le code de l'EC dans le document.",
                ],
            ];
        }

        // ── 2. Le semestre de l'EC s'accorde-t-il au niveau de la filière ? ──
        $attendus = $this->semestres->getSemestersForNiveau((string) $this->filiere->niveau);

        if ($attendus !== [] && !in_array((int) $ec->ue->semestre, $attendus, true)) {
            $liste = implode(' ou ', array_map(fn ($n) => "S{$n}", $attendus));
            $motifs[] = "L'EC {$ec->code} est rattaché au semestre S{$ec->ue->semestre}, "
                . "incompatible avec la filière {$this->filiere->code} ({$this->filiere->niveau}), "
                . "qui couvre {$liste}.";
        }

        // ── 3. La salle ─────────────────────────────────────────────────
        $salle = $this->resoudreSalle($creneau['salle'] ?? null);

        if (!empty($creneau['salle']) && $salle === null) {
            // Non bloquant : la colonne salle_libelle existe précisément pour
            // conserver un lieu que le référentiel ne connaît pas (« Zone Master
            // A2 » n'est pas une salle au sens du système). Mais on le signale,
            // car sans salle_id aucun conflit de salle ne pourra être détecté.
            $motifs[] = "La salle « {$creneau['salle']} » n'est pas au référentiel : "
                . 'elle sera conservée en texte libre, et les conflits de salle ne '
                . 'pourront pas être détectés pour ce créneau.';
        }

        $normalise = [
            'ec_id'         => $ec->id,
            'ec_code'       => $ec->code,
            'filiere_id'    => $this->filiere->id,
            'jour_semaine'  => $creneau['jour_semaine'],
            'heure_debut'   => $creneau['heure_debut'],
            'heure_fin'     => $creneau['heure_fin'],
            'salle_id'      => $salle?->id,
            'salle_libelle' => $salle === null ? ($creneau['salle'] ?? null) : null,
            'type_cours'    => $this->typeCours($creneau['type_seance'] ?? null),
            'enseignants'   => $creneau['enseignants'] ?? [],
            'date'          => $creneau['date'] ?? null,
        ];

        // ── 4. Doublons et conflits ─────────────────────────────────────
        $doublon = $this->doublon($normalise);

        if ($doublon !== null) {
            return ['statut' => self::DOUBLON, 'creneau' => $normalise, 'motifs' => [$doublon]];
        }

        $conflits = $this->conflits($normalise);

        if ($conflits !== []) {
            return ['statut' => self::CONFLIT, 'creneau' => $normalise, 'motifs' => $conflits];
        }

        if ($motifs !== []) {
            // Des remarques sans conflit : le créneau est utilisable mais mérite
            // une relecture.
            return ['statut' => self::AMBIGU, 'creneau' => $normalise, 'motifs' => $motifs];
        }

        $this->lot[] = $normalise;

        return ['statut' => self::VALIDE, 'creneau' => $normalise, 'motifs' => []];
    }

    /**
     * @param  array<string, mixed> $creneau
     * @return Ec|list<Ec>|null  L'EC, une liste si ambigu, null si introuvable.
     */
    private function resoudreEc(array $creneau): Ec|array|null
    {
        // Le code d'abord : c'est l'identification sans ambiguïté.
        if (!empty($creneau['ec_code'])) {
            $cible = $this->clef((string) $creneau['ec_code']);
            $parCode = $this->ecs->filter(fn (Ec $e) => $this->clef($e->code) === $cible);

            if ($parCode->count() === 1) {
                return $parCode->first();
            }
        }

        if (empty($creneau['ec_libelle'])) {
            return null;
        }

        $cible = $this->clef((string) $creneau['ec_libelle']);

        // Correspondance exacte du libellé.
        $exact = $this->ecs->filter(fn (Ec $e) => $this->clef($e->intitule) === $cible);

        if ($exact->count() === 1) {
            return $exact->first();
        }

        if ($exact->count() > 1) {
            return $exact->values()->all();
        }

        // Correspondance par inclusion : les fiches abrègent (« Bases de données »
        // pour « Bases de données relationnelles »). On n'accepte qu'un candidat
        // unique — deux candidats sont une ambiguïté, pas un choix à faire.
        $partiel = $this->ecs->filter(function (Ec $e) use ($cible) {
            $intitule = $this->clef($e->intitule);

            return $intitule !== '' && $cible !== ''
                && (str_contains($intitule, $cible) || str_contains($cible, $intitule));
        });

        if ($partiel->count() === 1) {
            return $partiel->first();
        }

        if ($partiel->count() > 1) {
            return $partiel->values()->all();
        }

        return null;
    }

    private function resoudreSalle(?string $libelle): ?Salle
    {
        if ($libelle === null || trim($libelle) === '') {
            return null;
        }

        $cible = $this->clef($libelle);

        return $this->salles->first(
            fn (Salle $s) => $this->clef((string) $s->code) === $cible
                || $this->clef((string) $s->nom) === $cible
        );
    }

    /**
     * Doublon : le même EC, le même jour, à la même heure. Présent deux fois
     * dans le fichier, ou déjà en base.
     *
     * @param array<string, mixed> $c
     */
    private function doublon(array $c): ?string
    {
        foreach ($this->lot as $deja) {
            if ($deja['ec_id'] === $c['ec_id']
                && $deja['jour_semaine'] === $c['jour_semaine']
                && $deja['heure_debut'] === $c['heure_debut']) {
                return "Ce créneau figure deux fois dans le document : {$c['ec_code']}, "
                    . $this->jour($c['jour_semaine']) . " à {$c['heure_debut']}.";
            }
        }

        $enBase = $this->existants->contains(
            fn (EmploiDuTemps $e) => $e->ec_id === $c['ec_id']
                && (int) $e->jour_semaine === $c['jour_semaine']
                && $this->hhmm($e->heure_debut) === $c['heure_debut']
        );

        if ($enBase) {
            return "Ce créneau existe déjà à l'emploi du temps : {$c['ec_code']}, "
                . $this->jour($c['jour_semaine']) . " à {$c['heure_debut']}.";
        }

        return null;
    }

    /**
     * Conflits d'occupation : salle, enseignant, et filière.
     *
     * Le conflit de FILIÈRE manquait aux deux chemins d'import : rien n'empêchait
     * de programmer deux cours différents au même moment pour la même promotion,
     * ce qui est pourtant la contrainte la plus élémentaire d'un emploi du temps.
     *
     * @param  array<string, mixed> $c
     * @return list<string>
     */
    private function conflits(array $c): array
    {
        $conflits = [];

        $chevauche = fn (string $d1, string $f1, string $d2, string $f2): bool => $d1 < $f2 && $d2 < $f1;

        // ── Conflits internes au lot ────────────────────────────────────
        foreach ($this->lot as $deja) {
            if ($deja['jour_semaine'] !== $c['jour_semaine']
                || !$chevauche($c['heure_debut'], $c['heure_fin'], $deja['heure_debut'], $deja['heure_fin'])) {
                continue;
            }

            if ($c['salle_id'] !== null && $deja['salle_id'] === $c['salle_id']) {
                $conflits[] = "Conflit de salle DANS le document : deux créneaux occupent la même salle "
                    . $this->jour($c['jour_semaine']) . " de {$deja['heure_debut']} à {$deja['heure_fin']}.";
            }

            // Les civilites sont retirees ICI aussi, et pas seulement par le
            // normalisateur : manquer un conflit parce qu'une source ecrit
            // « M. HOUNDJI » et l'autre « HOUNDJI » placerait la meme personne
            // sur deux cours simultanes.
            $communs = array_intersect(
                array_map([$this, 'clefEnseignant'], $c['enseignants']),
                array_map([$this, 'clefEnseignant'], $deja['enseignants'])
            );

            if ($communs !== []) {
                $conflits[] = "Conflit d'enseignant DANS le document : un même enseignant est "
                    . 'attendu sur deux créneaux simultanés ' . $this->jour($c['jour_semaine'])
                    . " de {$c['heure_debut']} à {$c['heure_fin']}.";
            }

            // Deux cours pour la même promotion au même moment.
            $conflits[] = 'Conflit de promotion DANS le document : deux cours différents sont '
                . 'programmés simultanément pour cette filière '
                . $this->jour($c['jour_semaine']) . " de {$c['heure_debut']} à {$c['heure_fin']}.";
        }

        // ── Conflits contre la base, sur les créneaux préchargés ────────
        $chevauchants = $this->existants->filter(
            fn (EmploiDuTemps $e) => (int) $e->jour_semaine === $c['jour_semaine']
                && $chevauche(
                    $c['heure_debut'], $c['heure_fin'],
                    $this->hhmm($e->heure_debut), $this->hhmm($e->heure_fin)
                )
        );

        if ($c['salle_id'] !== null) {
            $salleOccupee = $chevauchants->first(fn (EmploiDuTemps $e) => $e->salle_id === $c['salle_id']);

            if ($salleOccupee) {
                $conflits[] = 'Conflit de salle : cette salle est déjà occupée '
                    . $this->jour($c['jour_semaine'])
                    . ' de ' . $this->hhmm($salleOccupee->heure_debut)
                    . ' à ' . $this->hhmm($salleOccupee->heure_fin) . '.';
            }
        }

        $promotionOccupee = $chevauchants->first(
            fn (EmploiDuTemps $e) => $e->filiere_id === $c['filiere_id'] && $e->ec_id !== $c['ec_id']
        );

        if ($promotionOccupee) {
            $conflits[] = 'Conflit de promotion : cette filière a déjà un cours '
                . $this->jour($c['jour_semaine'])
                . ' de ' . $this->hhmm($promotionOccupee->heure_debut)
                . ' à ' . $this->hhmm($promotionOccupee->heure_fin) . '.';
        }

        return array_values(array_unique($conflits));
    }

    /**
     * Type de séance ramené aux valeurs du modèle. La colonne accepte du texte
     * libre, mais laisser passer « Evaluation par l'enseignant » en type de cours
     * rendrait toute statistique par type inexploitable.
     */
    private function typeCours(?string $brut): string
    {
        if ($brut === null) {
            return 'cours';
        }

        $clef = $this->clef($brut);

        // Les fiches ecrivent aussi bien « TP » que « Travaux pratiques ». Ne
        // chercher que l'abreviation classait le libelle complet en cours.
        return match (true) {
            str_contains($clef, 'travaux pratiques'), preg_match('/\btp\b/', $clef) === 1 => 'tp',
            str_contains($clef, 'travaux diriges'), preg_match('/\btd\b/', $clef) === 1   => 'td',
            str_contains($clef, 'evaluation'), str_contains($clef, 'examen'),
            str_contains($clef, 'controle')                                                => 'evaluation',
            default                                                                        => 'cours',
        };
    }

    /**
     * Heures ramenées à HH:mm. La colonne « time » rend « 08:00:00 » ; les
     * créneaux du lot portent « 08:00 ». Comparer les deux formes directement
     * n'aurait jamais détecté un seul doublon.
     */
    private function hhmm(mixed $heure): string
    {
        return substr((string) $heure, 0, 5);
    }

    private function jour(int $n): string
    {
        return EmploiDuTemps::JOURS[$n] ?? "jour {$n}";
    }

    /**
     * Clé de comparaison d'un nom d'enseignant : la clé ordinaire, débarrassée
     * des civilités, qui ne distinguent pas deux personnes.
     */
    private function clefEnseignant(string $nom): string
    {
        $nom = preg_replace('/^(m|mme|mlle|dr|pr|prof|monsieur|madame)\.?\s+/iu', '', trim($nom)) ?? $nom;

        return $this->clef($nom);
    }

    /** Clé de comparaison : minuscules, sans accent, sans ponctuation ni espaces superflus. */
    private function clef(string $valeur): string
    {
        $valeur = mb_strtolower(trim($valeur));

        $valeur = strtr($valeur, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'œ' => 'oe', "'" => ' ', '-' => ' ',
        ]);

        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[^a-z0-9 ]/u', ' ', $valeur) ?? '') ?? '');
    }
}
