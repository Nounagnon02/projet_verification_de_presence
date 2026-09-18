<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Evenement;
use App\Models\Groupe;
use App\Models\Salle;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Conflits d'occupation : une seule règle, pour l'emploi du temps comme pour
 * les séances datées.
 *
 * Deux occupations du même moment se gênent quand elles prennent la même
 * salle, le même enseignant ou le même public. Le public d'un cours, ce sont
 * toutes les filières qui suivent son UE, cours communs compris ; celui d'une
 * séance de groupe, ce groupe seul. Deux groupes différents ne se gênent pas ;
 * un groupe et toute sa promotion, si.
 *
 * La règle existait en trois exemplaires, chacun incomplet : l'import ignorait
 * les cours communs et les groupes, et comparait toutes les années entre
 * elles ; les séances ne regardaient que la salle ; la génération, rien.
 */
class Conflits
{
    /** Relations qu'il faut charger pour décrire une occupation. */
    public const CHARGEMENTS = ['ec.ue.filieres:id,code', 'groupe.filiere:id,code'];

    /**
     * Ce qu'occupe un créneau ou une séance déjà enregistrés.
     *
     * @return array<string, mixed>
     */
    public function occupation(EmploiDuTemps|Evenement $o): array
    {
        return $o instanceof EmploiDuTemps
            ? $this->decrire($o->id, $o->ec, $o->groupe, $o->salle_id, $o->salle?->nom ?? $o->salle_libelle, self::enseignants($o->enseignant), $o->heure_debut, $o->heure_fin)
            : $this->decrire($o->id, $o->ec, $o->groupe, $o->salle_id, $o->salleRef?->nom ?? $o->salle, [], $o->heure_debut, $o->heure_fin);
    }

    /**
     * Ce qu'occuperait un cours à un moment : ses filières (celle du groupe,
     * sinon toutes celles qui suivent l'UE), sa salle, ses enseignants.
     *
     * @param  list<string>  $enseignants
     * @return array<string, mixed>
     */
    public function decrire(?int $id, ?Ec $ec, ?Groupe $groupe, mixed $salleId, ?string $salle, array $enseignants, mixed $debut, mixed $fin): array
    {
        $filieres = $groupe
            ? [(int) $groupe->filiere_id => $groupe->filiere?->code ?? '?']
            : collect($ec?->ue?->filieres ?? [])->mapWithKeys(fn ($f) => [(int) $f->id => $f->code])->all();

        return [
            'id'          => $id,
            'ec_id'       => (int) $ec?->id,
            'ec_code'     => $ec?->code ?? '?',
            'filieres'    => $filieres,
            'groupe_id'   => $groupe?->id,
            'groupe'      => $groupe?->libelle,
            'salle_id'    => $salleId ? (int) $salleId : null,
            'salle'       => $salle,
            'enseignants' => $enseignants,
            'heure_debut' => substr((string) $debut, 0, 5),
            'heure_fin'   => substr((string) $fin, 0, 5),
        ];
    }

    /**
     * Conflits de $o avec d'autres occupations du même jour.
     *
     * @param  iterable<array<string, mixed>>  $autres
     * @return list<string>
     */
    public function entre(array $o, iterable $autres, string $quand): array
    {
        $motifs = [];

        foreach ($autres as $a) {
            if (($o['id'] !== null && $a['id'] === $o['id'])
                || !($o['heure_debut'] < $a['heure_fin'] && $a['heure_debut'] < $o['heure_fin'])) {
                continue;
            }

            $plage = "{$quand} de {$a['heure_debut']} à {$a['heure_fin']}";
            $cours = $a['ec_code'] . ($a['groupe'] ? " (groupe {$a['groupe']})" : '');

            if ($o['salle_id'] !== null && $o['salle_id'] === $a['salle_id']) {
                $motifs[] = 'Conflit de salle : ' . ($a['salle'] ?? 'la salle') . " est déjà occupée {$plage} par {$cours}.";
            }

            $communes = $this->publicCommun($o, $a);

            if ($communes !== []) {
                $motifs[] = $a['ec_id'] === $o['ec_id']
                    ? "Conflit EC : {$cours} est déjà programmé {$plage} pour " . implode(', ', $communes) . '.'
                    : 'Conflit de promotion : ' . implode(', ', $communes) . (count($communes) > 1 ? ' ont' : ' a') . " déjà {$cours} {$plage}.";
            }

            $profs = array_intersect(array_map([self::class, 'cle'], $o['enseignants']), array_map([self::class, 'cle'], $a['enseignants']));

            if ($profs !== []) {
                $motifs[] = "Conflit d'enseignant : un même enseignant est déjà attendu en {$cours} {$plage}.";
            }
        }

        return array_values(array_unique($motifs));
    }

    /**
     * Conflits d'une séance datée avec les autres séances du jour, annulées
     * exceptées.
     *
     * @param  array{date: string, heure_debut: string, heure_fin: string, salle_id?: mixed, groupe_id?: mixed}  $s
     * @return list<string>
     */
    public function pourSeance(Ec $ec, array $s, ?int $sauf = null): array
    {
        $ec->loadMissing('ue.filieres:id,code');
        $groupe = !empty($s['groupe_id']) ? Groupe::with('filiere:id,code')->find($s['groupe_id']) : null;
        $salle = !empty($s['salle_id']) ? Salle::whereKey($s['salle_id'])->value('nom') : null;
        $date = substr((string) $s['date'], 0, 10);

        $seance = $this->decrire($sauf, $ec, $groupe, $s['salle_id'] ?? null, $salle, [], $s['heure_debut'], $s['heure_fin']);

        $autres = Evenement::with([...self::CHARGEMENTS, 'salleRef:id,nom'])
            ->whereDate('date', $date)
            ->where('statut', '!=', 'annule')
            ->when($sauf, fn ($q) => $q->where('id', '!=', $sauf))
            ->where('heure_debut', '<', $seance['heure_fin'])
            ->where('heure_fin', '>', $seance['heure_debut'])
            ->get()
            ->map(fn (Evenement $e) => $this->occupation($e));

        return $this->entre($seance, $autres, 'le ' . Carbon::parse($date)->format('d/m/Y'));
    }

    /**
     * Paires en conflit parmi des occupations d'un même moment, pour le
     * rapport des conflits existants.
     *
     * @param  Collection<int, EmploiDuTemps|Evenement>  $modeles
     * @param  (callable(mixed, mixed): bool)|null  $compatibles  les deux se tiennent-elles en même temps ?
     * @return list<array{quand: string, a: array, b: array, motifs: list<string>}>
     */
    public function paires(Collection $modeles, string $quand, ?callable $compatibles = null): array
    {
        $modeles = $modeles->values();
        $occupations = $modeles->map(fn ($m) => $this->occupation($m))->all();
        $paires = [];

        foreach ($modeles as $i => $a) {
            for ($j = $i + 1; $j < count($modeles); $j++) {
                if ($compatibles && !$compatibles($a, $modeles[$j])) {
                    continue;
                }

                $motifs = $this->entre($occupations[$i], [$occupations[$j]], $quand);

                if ($motifs !== []) {
                    $paires[] = ['quand' => $quand, 'a' => $occupations[$i], 'b' => $occupations[$j], 'motifs' => $motifs];
                }
            }
        }

        return $paires;
    }

    /** Deux périodes de validité (vide : sans borne) ont-elles un jour en commun ? */
    public static function validitesSeRecouvrent(?string $du1, ?string $au1, ?string $du2, ?string $au2): bool
    {
        return ($du1 === null || $au2 === null || $du1 <= $au2)
            && ($du2 === null || $au1 === null || $du2 <= $au1);
    }

    /**
     * Enseignants d'un texte « HOUNDJI / Dr AGBO ». Les civilités ne
     * distinguent pas deux personnes : elles sont retirées.
     *
     * @return list<string>
     */
    public static function enseignants(?string $texte): array
    {
        $noms = [];

        foreach (preg_split('#\s*[/;,]\s*#u', (string) $texte) ?: [] as $nom) {
            $nom = trim(preg_replace('/^(M\.|Mme\.?|Mlle\.?|Dr\.?|Pr\.?|Prof\.?|Monsieur|Madame)\s+/iu', '', trim($nom)) ?? $nom);

            if ($nom !== '') {
                $noms[] = $nom;
            }
        }

        return array_values(array_unique($noms));
    }

    /**
     * Clé de comparaison d'un nom : sans civilité, en minuscules, sans accent
     * ni ponctuation. « M. HOUNDJI » et « HOUNDJI » sont la même personne ; les
     * sources ne s'accordent pas sur la civilité.
     */
    public static function cle(string $nom): string
    {
        $nom = preg_replace('/^(M\.|Mme\.?|Mlle\.?|Dr\.?|Pr\.?|Prof\.?|Monsieur|Madame)\s+/iu', '', trim($nom)) ?? $nom;
        $nom = strtr(mb_strtolower(trim($nom)), [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);

        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[^a-z0-9 ]/u', ' ', $nom) ?? '') ?? '');
    }

    /** Codes des filières dont le public se retrouve des deux côtés ; vide sinon. */
    private function publicCommun(array $a, array $b): array
    {
        if ($a['groupe_id'] !== null && $b['groupe_id'] !== null && $a['groupe_id'] !== $b['groupe_id']) {
            return [];
        }

        return array_values(array_intersect_key($a['filieres'], $b['filieres']));
    }
}
