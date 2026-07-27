<?php

namespace App\Traits;

use Illuminate\Http\Request;

/**
 * Trait pour les contrôleurs Admin Faculté.
 *
 * Fournit une méthode unifiée pour récupérer l'ID d'établissement
 * injecté par le middleware ScopeByEtablissement.
 *
 * Utilisation :
 *   $etablissementId = $this->getEtablissementId($request);
 *   // null si super admin, l'ID de la faculté si faculte_admin
 */
trait ScopedByEtablissement
{
    /**
     * Retourne l'ID d'établissement scope ou null (super admin).
     */
    protected function getEtablissementId(Request $request): ?int
    {
        return $request->get('scoped_etablissement_id');
    }

    /**
     * Applique un filtre etablissement_id sur une requête Eloquent
     * si l'utilisateur est un admin faculté scoped.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  Request  $request
     * @param  string   $column  Nom de la colonne etablissement_id
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function scopeQuery($query, Request $request, string $column = 'etablissement_id')
    {
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId) {
            $query->where($column, $etablissementId);
        }
        return $query;
    }

    /**
     * Vérifie qu'un modèle récupéré par route model binding appartient bien
     * à l'établissement de l'utilisateur, et coupe court sinon.
     *
     * Sans ce garde, le scoping ne s'applique qu'aux listes : un admin de
     * faculté peut lire/modifier/supprimer une ressource d'une autre faculté
     * en visant son ID directement.
     *
     * On renvoie 404 et non 403 pour ne pas confirmer l'existence de l'ID.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $path  Chemin vers l'établissement ('' si la colonne
     *                        etablissement_id est portée par le modèle,
     *                        sinon 'filiere' ou 'ue.filiere' par exemple)
     */
    protected function authorizeEtablissement($model, Request $request, string $path = ''): void
    {
        $etablissementId = $this->getEtablissementId($request);

        if (!$etablissementId) {
            return; // super admin : pas de cloisonnement
        }

        $target = $model;
        foreach (array_filter(explode('.', $path)) as $relation) {
            $target = $target?->{$relation};
        }

        if (!$target || (int) $target->etablissement_id !== (int) $etablissementId) {
            abort(404, 'Ressource non trouvée.');
        }
    }

    /**
     * Applique un filtre via une relation (ex: filiere.etablissement_id).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  Request  $request
     * @param  string   $relation  Nom de la relation (ex: 'filiere')
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function scopeViaRelation($query, Request $request, string $relation)
    {
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId) {
            $query->whereHas($relation, fn ($q) => $q->where('etablissement_id', $etablissementId));
        }
        return $query;
    }
}
