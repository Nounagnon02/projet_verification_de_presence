<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'code'           => $this->code,
            'intitule'       => $this->intitule,
            'semestre'       => $this->semestre,
            'volume_horaire' => $this->volume_horaire,
            'statut'         => $this->statut,
            'filiere'        => new FiliereResource($this->whenLoaded('filiere')),
            'annee'          => new AnneeAcademiqueResource($this->whenLoaded('annee')),
            'ecs'            => EcResource::collection($this->whenLoaded('ecs')),
            'ecs_count'      => $this->whenCounted('ecs'),
            'created_at'     => $this->created_at?->format('Y-m-d'),
        ];
    }
}
