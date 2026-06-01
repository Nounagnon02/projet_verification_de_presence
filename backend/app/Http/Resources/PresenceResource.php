<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'etudiant'   => new EtudiantResource($this->whenLoaded('etudiant')),
            'evenement'  => new EvenementResource($this->whenLoaded('evenement')),
            'heure_scan' => $this->heure_scan?->format('Y-m-d H:i:s'),
            'statut'     => $this->statut,
            'ip_address' => $this->ip_address,
            'latitude'   => $this->latitude,
            'longitude'  => $this->longitude,
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
