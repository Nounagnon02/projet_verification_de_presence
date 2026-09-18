<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use Carbon\Carbon;

/**
 * Ramène un créneau extrait d'un document à une forme canonique, ou explique
 * précisément pourquoi il n'y parvient pas.
 *
 * POURQUOI
 *
 * L'extraction exigeait une date calendaire au format YYYY-MM-DD, puis écartait
 * en silence tout créneau qui n'en avait pas. Or l'emploi du temps universitaire
 * habituel — celui que produit l'IFRI, vérifié sur un fichier réel du dépôt — est
 * HEBDOMADAIRE : ses colonnes sont « Lundi … Samedi », ses lignes des bandes
 * horaires « 8h – 13h ». Aucune date. Tous ces documents rendaient donc vide.
 *
 * Le reste du système modélise pourtant exactement cela : la table
 * emploi_du_temps porte une colonne jour_semaine, et ScheduleSlotResolver
 * traduit une date en jour de semaine. L'import IA était la seule pièce à
 * l'ignorer.
 *
 * Ce normalisateur accepte donc les DEUX formes, et ne rejette un créneau que
 * lorsqu'il manque une information sans laquelle il n'existe pas : le jour, ou
 * les heures.
 */
class NormalisateurCreneau
{
    /**
     * Jours de la semaine, en clés normalisées (sans accent, minuscules).
     *
     * Les variantes viennent des documents réels : abréviations, accents
     * inégaux, casse irrégulière. Un fichier produit sous Word par un
     * coordonnateur ne garantit aucune de ces trois choses.
     */
    private const JOURS = [
        'lundi' => 1, 'lun' => 1, 'lu' => 1, 'monday' => 1,
        'mardi' => 2, 'mar' => 2, 'ma' => 2, 'tuesday' => 2,
        'mercredi' => 3, 'mer' => 3, 'me' => 3, 'wednesday' => 3,
        'jeudi' => 4, 'jeu' => 4, 'je' => 4, 'thursday' => 4,
        'vendredi' => 5, 'ven' => 5, 've' => 5, 'friday' => 5,
        'samedi' => 6, 'sam' => 6, 'sa' => 6, 'saturday' => 6,
        'dimanche' => 7, 'dim' => 7, 'di' => 7, 'sunday' => 7,
    ];

    /**
     * Normalise un créneau brut.
     *
     * @param  array<string, mixed> $brut
     * @return array{ok: bool, creneau?: array<string, mixed>, motif?: string, champ?: string}
     */
    public function normaliser(array $brut): array
    {
        $libelle = $this->texte($brut, ['ec', 'ec_libelle', 'cours', 'matiere', 'intitule']);
        $code    = $this->texte($brut, ['ec_code', 'code_ec', 'code']);

        $ecId = isset($brut['ec_id']) && is_numeric($brut['ec_id']) ? (int) $brut['ec_id'] : null;

        if ($libelle === null && $code === null && $ecId === null) {
            return ['ok' => false, 'champ' => 'ec', 'motif' => "Aucun nom ni code de cours : le créneau ne désigne aucun enseignement."];
        }

        // Jour : depuis un nom de jour, sinon déduit d'une date calendaire.
        $date = $this->date($brut);
        $jour = $this->jourSemaine($brut) ?? ($date ? (int) $date->isoWeekday() : null);

        if ($jour === null) {
            return [
                'ok'    => false,
                'champ' => 'jour',
                'motif' => "Jour introuvable : ni jour de la semaine (« Lundi »…), ni date calendaire exploitable.",
            ];
        }

        $debut = $this->heure($brut, ['heure_debut', 'debut', 'heure_de_debut', 'start']);
        $fin   = $this->heure($brut, ['heure_fin', 'fin', 'heure_de_fin', 'end']);

        if ($debut === null || $fin === null) {
            return [
                'ok'    => false,
                'champ' => 'heure',
                'motif' => 'Horaires incomplets : un créneau sans heure de début ou de fin ne peut pas être placé.',
            ];
        }

        if ($debut >= $fin) {
            return [
                'ok'    => false,
                'champ' => 'heure',
                'motif' => "L'heure de fin ({$fin}) n'est pas postérieure à l'heure de début ({$debut}).",
            ];
        }

        return [
            'ok'      => true,
            'creneau' => [
                'ec_id'        => $ecId,
                'ec_libelle'   => $libelle,
                'ec_code'      => $code,
                'jour_semaine' => $jour,
                // Conservée quand le document en fournissait une : elle permet de
                // distinguer un planning ponctuel d'un créneau récurrent.
                'date'         => $date?->toDateString(),
                'heure_debut'  => $debut,
                'heure_fin'    => $fin,
                'salle'        => $this->texte($brut, ['salle', 'salle_libelle', 'salle_code', 'local', 'lieu']),
                // Choix fait sur l'écran de validation : une salle configurée, ou
                // aucune. Seul l'identifiant désigne une salle ; le nom lu ne sert
                // qu'à le proposer.
                'salle_id'     => isset($brut['salle_id']) && is_numeric($brut['salle_id']) ? (int) $brut['salle_id'] : null,
                'sans_salle'   => filter_var($brut['sans_salle'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'enseignants'  => $this->enseignants($brut),
                'filiere'      => $this->texte($brut, ['filiere', 'filiere_code', 'classe']),
                'type_seance'  => $this->texte($brut, ['type_cours', 'type_seance', 'type', 'nature']),
                // Groupe de TD ou de TP, par son libellé (« G1 ») ; version de
                // l'emploi du temps (« à partir du 15 juin »).
                'groupe'       => $this->texte($brut, ['groupe', 'groupe_td', 'groupe_tp']),
                'valide_du'    => $this->date($brut, ['valide_du', 'valable_du', 'a_partir_du'])?->toDateString(),
                'valide_au'    => $this->date($brut, ['valide_au', 'valable_au', 'jusqu_au'])?->toDateString(),
            ],
        ];
    }

    /** Premier champ non vide parmi une liste de clés possibles. */
    private function texte(array $brut, array $cles): ?string
    {
        foreach ($cles as $cle) {
            if (!isset($brut[$cle])) {
                continue;
            }

            $valeur = is_array($brut[$cle]) ? implode(' / ', $brut[$cle]) : (string) $brut[$cle];
            $valeur = $this->nettoyer($valeur);

            if ($valeur !== '') {
                return $valeur;
            }
        }

        return null;
    }

    /**
     * Nettoyage des parasites courants : espaces insécables, tirets
     * typographiques, apostrophes courbes, espaces multiples. Un document Word
     * en produit en abondance, et ils font échouer toute comparaison exacte.
     */
    private function nettoyer(string $valeur): string
    {
        $valeur = str_replace(
            ["\u{00A0}", "\u{202F}", "\u{2011}", "\u{2013}", "\u{2014}", "\u{2019}", "\u{2018}"],
            [' ', ' ', '-', '-', '-', "'", "'"],
            $valeur
        );

        return trim(preg_replace('/\s+/u', ' ', $valeur) ?? '');
    }

    private function jourSemaine(array $brut): ?int
    {
        foreach (['jour', 'jour_semaine', 'day', 'jour_de_la_semaine'] as $cle) {
            if (!isset($brut[$cle])) {
                continue;
            }

            $valeur = $brut[$cle];

            // Déjà numérique : on accepte 1..7 tel quel.
            if (is_int($valeur) || (is_string($valeur) && preg_match('/^[1-7]$/', trim($valeur)))) {
                return (int) $valeur;
            }

            if (!is_string($valeur)) {
                continue;
            }

            $clef = $this->sansAccent($this->nettoyer($valeur));

            // « Lundi 09/03 » : on isole le premier mot connu.
            foreach (preg_split('/[^a-z]+/', $clef) ?: [] as $mot) {
                if ($mot !== '' && isset(self::JOURS[$mot])) {
                    return self::JOURS[$mot];
                }
            }
        }

        return null;
    }

    private function date(array $brut, array $cles = ['date', 'jour', 'date_seance']): ?Carbon
    {
        foreach ($cles as $cle) {
            if (empty($brut[$cle]) || !is_string($brut[$cle])) {
                continue;
            }

            $valeur = $this->nettoyer($brut[$cle]);

            // Formats explicites d'abord, pour éviter les lectures fantaisistes
            // de Carbon::parse sur une chaîne comme « Lundi », qui fabriquerait
            // une date arbitraire — donc un cours fantôme.
            //
            // createFromFormat LÈVE en mode strict au lieu de renvoyer false :
            // l'exception est donc attendue et fait partie du contrôle.
            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y'] as $format) {
                try {
                    $d = Carbon::createFromFormat($format, $valeur);
                } catch (\Throwable) {
                    continue;
                }

                // Le re-formatage doit rendre la chaîne d'origine : sans cette
                // vérification, « 2026-13-45 » passerait en débordant sur le mois
                // suivant.
                if ($d instanceof Carbon && $d->format($format) === $valeur) {
                    return $d->startOfDay();
                }
            }
        }

        return null;
    }

    /**
     * Heures. Accepte « 08:00 », « 8h », « 8h30 », « 8 h 30 », « 08.00 ».
     *
     * La notation française « 8h » est celle des fiches réelles : « 8h – 13h ».
     * L'ancien pipeline n'acceptait que HH:mm et perdait donc tout.
     */
    private function heure(array $brut, array $cles): ?string
    {
        foreach ($cles as $cle) {
            if (!isset($brut[$cle])) {
                continue;
            }

            $valeur = $this->nettoyer((string) $brut[$cle]);

            if ($valeur === '') {
                continue;
            }

            if (preg_match('/^(\d{1,2})\s*[h:.]\s*(\d{1,2})?$/iu', $valeur, $m)) {
                $heures   = (int) $m[1];
                $minutes  = (int) ($m[2] ?? 0);

                if ($heures <= 23 && $minutes <= 59) {
                    return sprintf('%02d:%02d', $heures, $minutes);
                }
            }

            // « 8 » seul : une heure pleine.
            if (preg_match('/^(\d{1,2})$/', $valeur, $m) && (int) $m[1] <= 23) {
                return sprintf('%02d:00', (int) $m[1]);
            }
        }

        return null;
    }

    /**
     * Enseignants. Les fiches réelles en listent plusieurs, séparés par « / ».
     *
     * Aucune table « enseignants » n'existe dans le modèle : l'information est
     * conservée en texte afin de ne pas être perdue du document source, et
     * normalisée pour permettre la détection des chevauchements.
     *
     * @return list<string>
     */
    private function enseignants(array $brut): array
    {
        $brut2 = $brut['enseignants'] ?? $brut['enseignant'] ?? $brut['professeur'] ?? null;

        if ($brut2 === null) {
            return [];
        }

        $liste = is_array($brut2) ? $brut2 : (preg_split('#\s*[/;,]\s*#u', (string) $brut2) ?: []);

        $noms = [];

        foreach ($liste as $nom) {
            $nom = $this->nettoyer((string) $nom);
            // Les civilités ne distinguent pas deux personnes.
            $nom = preg_replace('/^(M\.|Mme\.?|Mlle\.?|Dr\.?|Pr\.?|Prof\.?)\s*/iu', '', $nom) ?? $nom;
            $nom = trim($nom);

            if ($nom !== '') {
                $noms[] = $nom;
            }
        }

        return array_values(array_unique($noms));
    }

    private function sansAccent(string $valeur): string
    {
        $valeur = mb_strtolower($valeur);

        return strtr($valeur, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
    }
}
