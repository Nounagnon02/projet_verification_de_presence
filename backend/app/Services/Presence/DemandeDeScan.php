<?php

declare(strict_types=1);

namespace App\Services\Presence;

use App\Models\Etudiant;

/**
 * Ce qu'un étudiant soumet pour valider sa présence.
 *
 * L'étudiant est un OBJET, pas un identifiant posté : le scan est désormais
 * authentifié (jeton Sanctum de capacité « etudiant »). Auparavant le corps de
 * la requête portait « identifiant_unique », et cet identifiant est déterministe
 * — NOM_PRENOM_MATRICULE_FILIERE_ANNEE, voir IdentifiantService : n'importe quel
 * camarade de promotion pouvait le reconstituer et pointer à la place d'un
 * absent. Le seul moyen de désigner l'étudiant est maintenant son jeton.
 *
 * L'adresse IP est renseignée par le contrôleur à partir de la requête : elle
 * sert de seconde preuve de réseau (voir FacteurReseau) et n'est fiable que
 * parce que le répartiteur de Render est déclaré proxy de confiance dans
 * bootstrap/app.php.
 */
final class DemandeDeScan
{
    public function __construct(
        public readonly Etudiant $etudiant,
        public readonly string $jeton,
        public readonly string $empreinteAppareil,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?string $ssid = null,
        public readonly ?string $bssid = null,
        public readonly ?string $ip = null,
    ) {
    }
}
