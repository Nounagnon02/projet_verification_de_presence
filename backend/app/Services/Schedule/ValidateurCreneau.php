<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Filiere;
use App\Models\Groupe;
use App\Models\Salle;
use App\Services\CorrespondanceSalles;
use App\Services\Groupes\GestionGroupes;
use App\Services\Planning\Conflits;
use App\Services\SemesterService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Valide un créneau d'emploi du temps contre le modèle académique, et détecte
 * les conflits.
 *
 * POURQUOI CE SERVICE EXISTE
 *
 * Les créneaux s'écrivent par l'import CSV, par l'import IA et par la grille de
 * l'emploi du temps. Chaque chemin avait ses règles, et la fiabilité du système
 * valait celle du plus faible. Ce service les porte une seule fois ; les
 * conflits d'occupation (salle, public, enseignant) viennent de
 * Planning\Conflits, que les séances datées partagent.
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

    /**
     * Salles actives de l'établissement de la filière, et elles seules : on
     * cherchait dans toutes les salles du système, si bien qu'un créneau pouvait
     * être rattaché à la salle homonyme d'une autre faculté.
     *
     * @var Collection<int, Salle>
     */
    private Collection $salles;

    /** @var Collection<int, Groupe> groupes de la filière pour l'année */
    private Collection $groupes;

    /**
     * Créneaux de la MÊME année, préchargés une fois. On comparait toutes les
     * années entre elles : après la préparation de l'année suivante, qui
     * recopie l'emploi du temps, chaque créneau de la nouvelle année entrait en
     * conflit avec son double de l'année écoulée.
     *
     * @var Collection<int, EmploiDuTemps>
     */
    private Collection $existants;

    /** @var array<int, array<string, mixed>> occupation de chaque créneau existant */
    private array $occupations = [];

    private ?Filiere $filiere = null;

    private ?int $anneeId = null;

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
        private readonly Conflits $conflits = new Conflits(),
        private readonly GestionGroupes $gestionGroupes = new GestionGroupes(),
    ) {}

    /**
     * Prépare la validation pour une filière et une année données. $sauf :
     * le créneau en cours de modification, qui ne se gêne pas lui-même.
     *
     * @return array{ok: bool, motif?: string}
     */
    public function preparer(int $filiereId, int $anneeId, ?int $sauf = null): array
    {
        $this->filiere = Filiere::find($filiereId);
        $this->anneeId = $anneeId;
        $this->lot = [];

        if ($this->filiere === null) {
            return ['ok' => false, 'motif' => "Filière {$filiereId} introuvable."];
        }

        // Les ECs que suit CETTE filière CETTE année, cours communs compris. Un
        // EC existant ailleurs n'est pas un EC valide ici.
        $this->ecs = Ec::query()
            ->with('ue.filieres:id,code')
            ->whereHas('ue', fn ($q) => $q->where('annee_id', $anneeId)->whereHas('filieres', fn ($f) => $f->where('filieres.id', $filiereId)))
            ->get();

        $this->salles = $this->filiere->etablissement_id
            ? Salle::where('etablissement_id', $this->filiere->etablissement_id)->where('actif', true)->get()
            : collect();

        $this->groupes = Groupe::with('filiere:id,code')->where('filiere_id', $filiereId)->where('annee_id', $anneeId)->get();

        $this->existants = EmploiDuTemps::with([...Conflits::CHARGEMENTS, 'salle:id,nom'])
            ->where('annee_id', $anneeId)
            ->when($sauf, fn ($q) => $q->where('id', '!=', $sauf))
            ->get();

        $this->occupations = $this->existants
            ->mapWithKeys(fn (EmploiDuTemps $e) => [$e->id => $this->conflits->occupation($e)])
            ->all();

        return ['ok' => true];
    }

    /**
     * Un créneau enregistré ailleurs pendant le même traitement (une autre
     * filière du fichier CSV) : il occupe sa salle et son public ici aussi.
     */
    public function connaitreCreneau(EmploiDuTemps $creneau): void
    {
        if ((int) $creneau->annee_id !== (int) $this->anneeId || isset($this->occupations[$creneau->id])) {
            return;
        }

        $creneau->loadMissing([...Conflits::CHARGEMENTS, 'salle:id,nom']);
        $this->existants->push($creneau);
        $this->occupations[$creneau->id] = $this->conflits->occupation($creneau);
    }

    /** Une salle créée pendant l'import (CSV) devient connue du lot. */
    public function connaitreSalle(Salle $salle): void
    {
        if (!$this->salles->contains('id', $salle->id)) {
            $this->salles->push($salle);
        }
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
        // Elle se désigne par son identifiant, choisi sur l'écran de
        // validation, ou par un « aucune » explicite. Le nom lu par l'IA n'est
        // jamais enregistré tel quel : il peut être mal lu (« Amhpi C »), et un
        // nom seul ne permet ni le contrôle au scan ni la détection de double
        // réservation. Un nom sans choix laisse donc le créneau en attente.
        $salle = null;

        if (!empty($creneau['salle_id'])) {
            $salle = $this->salles->firstWhere('id', (int) $creneau['salle_id']);

            if ($salle === null) {
                return [
                    'statut' => self::INVALIDE,
                    'motifs' => [
                        "La salle choisie n'existe pas dans l'établissement de la filière "
                        . "{$this->filiere->code}, ou elle est désactivée.",
                    ],
                ];
            }
        } elseif (empty($creneau['sans_salle']) && !empty($creneau['salle'])) {
            $reconnue = $this->reconnaitreSalle((string) $creneau['salle']);

            $motifs[] = "Salle « {$creneau['salle']} » à choisir"
                . ($reconnue ? " : elle correspond à « {$reconnue->nom} », confirmez-la" : '')
                . ' — sélectionnez une salle, créez-la, ou choisissez « Aucune — QR seul ».';
        }

        // ── 4. Le groupe, pour un TD ou un TP ───────────────────────────
        $type = $this->typeCours($creneau['type_seance'] ?? $creneau['type_cours'] ?? null);
        [$groupe, $refusGroupe] = $this->groupe($creneau, $type, $ec);

        if ($refusGroupe !== null) {
            return ['statut' => self::INVALIDE, 'motifs' => [$refusGroupe]];
        }

        // ── 5. La validité : les versions successives d'un emploi du temps ──
        [$du, $au, $refusValidite] = $this->validite($creneau);

        if ($refusValidite !== null) {
            return ['statut' => self::INVALIDE, 'motifs' => [$refusValidite]];
        }

        $enseignants = array_values(array_filter(array_map('strval', (array) ($creneau['enseignants'] ?? []))));

        $normalise = [
            'ec_id'         => $ec->id,
            'ec_code'       => $ec->code,
            'filiere_id'    => $this->filiere->id,
            'annee_id'      => $this->anneeId,
            'jour_semaine'  => (int) $creneau['jour_semaine'],
            'heure_debut'   => $creneau['heure_debut'],
            'heure_fin'     => $creneau['heure_fin'],
            'salle_id'      => $salle?->id,
            // Copie du nom de la salle configurée : les séances générées et les
            // écrans l'affichent. Jamais un nom libre.
            'salle_libelle' => $salle?->nom,
            'type_cours'    => $type,
            'groupe_id'     => $groupe?->id,
            'groupe'        => $groupe?->libelle,
            'enseignants'   => $enseignants,
            'enseignant'    => $enseignants !== [] ? implode(' / ', $enseignants) : null,
            'valide_du'     => $du,
            'valide_au'     => $au,
            'date'          => $creneau['date'] ?? null,
        ];

        // ── 6. Doublons et conflits ─────────────────────────────────────
        $doublon = $this->doublon($normalise);

        if ($doublon !== null) {
            return ['statut' => self::DOUBLON, 'creneau' => $normalise, 'motifs' => [$doublon]];
        }

        $occupation = $this->conflits->decrire(null, $ec, $groupe, $salle?->id, $salle?->nom, $enseignants, $normalise['heure_debut'], $normalise['heure_fin']);
        $conflits = $this->conflits($normalise, $occupation);

        if ($conflits !== []) {
            return ['statut' => self::CONFLIT, 'creneau' => $normalise, 'motifs' => $conflits];
        }

        if ($motifs !== []) {
            // Des remarques sans conflit : le créneau est utilisable mais mérite
            // une relecture.
            return ['statut' => self::AMBIGU, 'creneau' => $normalise, 'motifs' => $motifs];
        }

        $this->lot[] = $normalise + ['occupation' => $occupation];

        return ['statut' => self::VALIDE, 'creneau' => $normalise, 'motifs' => []];
    }

    /**
     * @param  array<string, mixed> $creneau
     * @return Ec|list<Ec>|null  L'EC, une liste si ambigu, null si introuvable.
     */
    private function resoudreEc(array $creneau): Ec|array|null
    {
        // L'identifiant d'abord (grille de l'emploi du temps), puis le code.
        if (!empty($creneau['ec_id'])) {
            return $this->ecs->firstWhere('id', (int) $creneau['ec_id']);
        }

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

    /**
     * Groupe visé : par son identifiant (grille), ou par son libellé (import),
     * cherché parmi les groupes de la filière. Seul un TD ou un TP en a un.
     *
     * @return array{0: ?Groupe, 1: ?string}  le groupe, ou le motif du refus
     */
    private function groupe(array $creneau, string $type, Ec $ec): array
    {
        $id = !empty($creneau['groupe_id']) ? (int) $creneau['groupe_id'] : null;

        if ($id === null && !empty($creneau['groupe'])) {
            $libelle = mb_strtoupper(trim((string) $creneau['groupe']));
            $candidats = $this->groupes->where('libelle', $libelle);
            $id = ($candidats->firstWhere('type', $type) ?? $candidats->first())?->id;

            if ($id === null) {
                return [null, "Le groupe « {$libelle} » n'existe pas dans la filière {$this->filiere->code} cette année : créez-le dans Étudiants › Groupes, ou retirez-le du créneau."];
            }
        }

        if ($id === null) {
            return [null, null];
        }

        try {
            $groupe = $this->gestionGroupes->verifierPourSeance($id, $type, $ec);
            $groupe?->loadMissing('filiere:id,code');

            return [$groupe, null];
        } catch (ValidationException $e) {
            return [null, (string) collect($e->errors())->flatten()->first()];
        }
    }

    /**
     * Période de validité du créneau (vide : sans borne), en AAAA-MM-JJ.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string}  début, fin, motif du refus
     */
    private function validite(array $creneau): array
    {
        $du = ($creneau['valide_du'] ?? null) ?: null;
        $au = ($creneau['valide_au'] ?? null) ?: null;

        foreach ([$du, $au] as $date) {
            if ($date !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
                return [null, null, "Date de validité illisible : « {$date} ». Attendu : AAAA-MM-JJ."];
            }
        }

        if ($du !== null && $au !== null && $au < $du) {
            return [null, null, "La validité du créneau finit le {$au}, avant de commencer le {$du}."];
        }

        return [$du, $au, null];
    }

    /** Salle de l'établissement que désigne ce nom, pour le suggérer. */
    private function reconnaitreSalle(string $libelle): ?Salle
    {
        $cible = CorrespondanceSalles::cle($libelle);

        return $cible === '' ? null : $this->salles->first(
            fn (Salle $s) => CorrespondanceSalles::cle($s->code) === $cible
                || CorrespondanceSalles::cle($s->nom) === $cible
        );
    }

    /**
     * Doublon : le même EC, pour le même public (groupe), le même jour, à la
     * même heure, sur des validités qui se recouvrent. Présent deux fois dans
     * le fichier, ou déjà en base.
     *
     * @param array<string, mixed> $c
     */
    private function doublon(array $c): ?string
    {
        $meme = fn (int $ecId, int $jour, string $debut, ?int $groupeId, ?string $du, ?string $au): bool => $ecId === $c['ec_id']
            && $jour === $c['jour_semaine']
            && $debut === $c['heure_debut']
            && $groupeId === $c['groupe_id']
            && Conflits::validitesSeRecouvrent($du, $au, $c['valide_du'], $c['valide_au']);

        foreach ($this->lot as $deja) {
            if ($meme($deja['ec_id'], $deja['jour_semaine'], $deja['heure_debut'], $deja['groupe_id'], $deja['valide_du'], $deja['valide_au'])) {
                return "Ce créneau figure deux fois dans le document : {$c['ec_code']}, "
                    . $this->jour($c['jour_semaine']) . " à {$c['heure_debut']}.";
            }
        }

        $enBase = $this->existants->contains(fn (EmploiDuTemps $e) => $meme(
            (int) $e->ec_id,
            (int) $e->jour_semaine,
            $this->hhmm($e->heure_debut),
            $e->groupe_id ? (int) $e->groupe_id : null,
            $e->valide_du?->toDateString(),
            $e->valide_au?->toDateString(),
        ));

        if ($enBase) {
            return "Ce créneau existe déjà à l'emploi du temps : {$c['ec_code']}, "
                . $this->jour($c['jour_semaine']) . " à {$c['heure_debut']}.";
        }

        return null;
    }

    /**
     * Conflits d'occupation avec le lot et la base : même jour, validités qui
     * se recouvrent, puis la règle commune (salle, public, enseignant).
     *
     * @param  array<string, mixed> $c
     * @return list<string>
     */
    private function conflits(array $c, array $occupation): array
    {
        $memeMoment = fn (int $jour, ?string $du, ?string $au): bool => $jour === $c['jour_semaine']
            && Conflits::validitesSeRecouvrent($du, $au, $c['valide_du'], $c['valide_au']);

        $autres = [];

        foreach ($this->lot as $deja) {
            if ($memeMoment($deja['jour_semaine'], $deja['valide_du'], $deja['valide_au'])) {
                $autres[] = $deja['occupation'];
            }
        }

        foreach ($this->existants as $e) {
            if ($memeMoment((int) $e->jour_semaine, $e->valide_du?->toDateString(), $e->valide_au?->toDateString())) {
                $autres[] = $this->occupations[$e->id];
            }
        }

        return $this->conflits->entre($occupation, $autres, $this->jour($c['jour_semaine']));
    }

    /**
     * Type de séance ramené aux valeurs du modèle. La colonne accepte du texte
     * libre, mais laisser passer « Evaluation par l'enseignant » en type de cours
     * rendrait toute statistique par type inexploitable.
     */
    private function typeCours(?string $brut): string
    {
        // Une seule normalisation, partagée avec les imports et la génération.
        return \App\Support\TypeCours::normaliser($brut);
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
