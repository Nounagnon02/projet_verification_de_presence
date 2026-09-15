<?php

namespace App\Services;

use App\Models\Salle;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reconnaissance des salles d'un établissement à partir d'un nom lu : dans un
 * fichier CSV, dans un document analysé par l'IA, ou saisi autrefois à la main.
 *
 * Trois chemins lisaient des noms de salle, chacun à sa manière. L'import CSV
 * comparait à l'identique et dans TOUS les établissements : « amphi c » ne
 * trouvait pas « Amphi C », et « Amphi C » pouvait désigner la salle d'une autre
 * faculté. Le validateur de l'import IA cherchait lui aussi partout. Seule la
 * reprise salles:rattacher se limitait à l'établissement : sa règle devient la
 * règle commune.
 *
 * Une salle n'est cherchée que parmi celles de l'établissement, sur son nom ou
 * son code, sans tenir compte de la casse, des accents, de la ponctuation ni
 * des espaces.
 */
class CorrespondanceSalles
{
    /** @var Collection<int, Salle> */
    private Collection $salles;

    private function __construct(private readonly int $etablissementId)
    {
        // Actives ou non : une salle désactivée du même nom ne doit pas être
        // recréée en double. C'est à l'appelant de refuser de l'utiliser.
        $this->salles = Salle::where('etablissement_id', $etablissementId)->get();
    }

    public static function pour(int $etablissementId): self
    {
        return new self($etablissementId);
    }

    /** La salle de l'établissement qui porte ce nom ou ce code, active ou non. */
    public function trouver(?string $nom): ?Salle
    {
        $cle = self::cle($nom);

        if ($cle === '') {
            return null;
        }

        return $this->salles->first(
            fn (Salle $s) => self::cle($s->nom) === $cle || self::cle($s->code) === $cle
        );
    }

    /**
     * La salle qui porte ce nom, créée si l'établissement n'en a aucune.
     *
     * Une salle créée ici n'a ni coordonnées GPS ni réseau Wi-Fi : ces données
     * ne se devinent pas, elles se relèvent sur place. Elle ne vérifie donc que
     * le QR code jusqu'à ce que l'administration la complète. Elle est aussitôt
     * connue de cette instance : un même nom sur dix lignes ne crée qu'une salle.
     *
     * @return array{0: Salle, 1: bool}  la salle, et vrai si elle vient d'être créée
     */
    public function trouverOuCreer(string $nom): array
    {
        if ($salle = $this->trouver($nom)) {
            return [$salle, false];
        }

        $nom = self::nettoyer($nom);

        $salle = Salle::create([
            'etablissement_id' => $this->etablissementId,
            'nom'              => $nom,
            'code'             => self::codeUnique($nom),
            'actif'            => true,
        ]);

        $this->salles->push($salle);

        return [$salle, true];
    }

    /** Forme de comparaison : sans casse, accents, ponctuation ni espaces superflus. */
    public static function cle(?string $texte): string
    {
        $texte = Str::lower(Str::ascii((string) $texte));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $texte) ?? '');
    }

    /** Nom tel qu'il sera enregistré : espaces superflus retirés, casse conservée. */
    public static function nettoyer(string $nom): string
    {
        return trim(preg_replace('/\s+/u', ' ', $nom) ?? $nom);
    }

    /** Code dérivé du nom (« Labo Info 1 » → « LABO-INFO-1 »), unique dans la table. */
    public static function codeUnique(string $nom): string
    {
        // salles.code est limité à 50 caractères : place gardée pour un suffixe.
        $base = substr(Str::upper(Str::slug(Str::ascii($nom))), 0, 44) ?: 'SALLE';
        $code = $base;

        for ($i = 2; Salle::where('code', $code)->exists(); $i++) {
            $code = "{$base}-{$i}";
        }

        return $code;
    }
}
