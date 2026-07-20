<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'code'           => $this->code,
            'intitule'       => $this->intitule,
            'volume_horaire' => $this->volume_horaire,
            'statut'         => $this->statut,
            'ue'             => new UeResource($this->whenLoaded('ue')),
            'evenements'     => EvenementResource::collection($this->whenLoaded('evenements')),
            'created_at'     => $this->created_at?->format('Y-m-d'),
        ];
    }
}
