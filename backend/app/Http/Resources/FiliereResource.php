<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiliereResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'code'           => $this->code,
            'intitule'       => $this->intitule,
            'niveau'         => $this->niveau,
            'etudiants_count' => $this->whenCounted('etudiants'),
            'ues_count'      => $this->whenCounted('ues'),
            'ues'            => UeResource::collection($this->whenLoaded('ues')),
            'created_at'     => $this->created_at?->format('Y-m-d'),
        ];
    }
}
