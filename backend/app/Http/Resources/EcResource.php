<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributs = $this->resource->getAttributes();

        return [
            'id'             => $this->id,
            'code'           => $this->code,
            'intitule'       => $this->intitule,
            'volume_horaire' => $this->volume_horaire,
            'statut'         => $this->statut,
            'ue'             => new UeResource($this->whenLoaded('ue')),
            'evenements'     => EvenementResource::collection($this->whenLoaded('evenements')),
            // Posés par AvancementCours::appliquer() ou EcController::index :
            // absents tant que rien ne les a calculés pour cette requête.
            'heures_faites'              => $this->when(array_key_exists('heures_faites', $attributs), fn () => $this->heures_faites),
            'heures_reservees'           => $this->when(array_key_exists('heures_reservees', $attributs), fn () => $this->heures_reservees),
            'heures_restantes'           => $this->when(array_key_exists('heures_restantes', $attributs), fn () => $this->heures_restantes),
            'heures_restantes_par_type'  => $this->when(array_key_exists('heures_restantes_par_type', $attributs), fn () => $this->heures_restantes_par_type),
            'duree_max_seance'           => $this->when(array_key_exists('duree_max_seance', $attributs), fn () => $this->duree_max_seance),
            'created_at'     => $this->created_at?->format('Y-m-d'),
        ];
    }
}
