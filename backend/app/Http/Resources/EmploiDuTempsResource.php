<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmploiDuTempsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributs = $this->resource->getAttributes();

        return [
            'id'           => $this->id,
            'ec_id'        => $this->ec_id,
            'ec'           => ['id' => $this->ec?->id, 'code' => $this->ec?->code, 'intitule' => $this->ec?->intitule],
            'ue'           => ['code' => $this->ec?->ue?->code, 'semestre' => $this->ec?->ue?->semestre],
            'filieres'     => $this->ec?->ue?->filieres->pluck('code')->values()->all() ?? [],
            'filiere_id'   => $this->filiere_id,
            'annee_id'     => $this->annee_id,
            'jour_semaine' => $this->jour_semaine,
            'heure_debut'  => substr((string) $this->heure_debut, 0, 5),
            'heure_fin'    => substr((string) $this->heure_fin, 0, 5),
            'type_cours'   => $this->type_cours,
            'salle_id'     => $this->salle_id,
            'salle'        => $this->salle?->nom ?? $this->salle_libelle,
            'groupe_id'    => $this->groupe_id,
            'groupe'       => $this->groupe?->libelle,
            'enseignant'   => $this->enseignant,
            'valide_du'    => $this->valide_du?->toDateString(),
            'valide_au'    => $this->valide_au?->toDateString(),
            // Posé par le contrôleur avant de construire la ressource, pour un
            // créneau donné : absent (donc vide) tant qu'aucun calcul de
            // conflits ne lui a été associé.
            'conflits'     => array_key_exists('conflits', $attributs) ? $this->conflits : [],
        ];
    }
}
