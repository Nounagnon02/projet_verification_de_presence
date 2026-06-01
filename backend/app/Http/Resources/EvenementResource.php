<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvenementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'date'            => $this->date?->format('Y-m-d'),
            'heure_debut'     => $this->heure_debut,
            'heure_fin'       => $this->heure_fin,
            'salle'           => $this->salle,
            'statut'          => $this->statut,
            'ec'              => new EcResource($this->whenLoaded('ec')),
            'filiere'         => new FiliereResource($this->whenLoaded('filiere')),
            'presences_count' => $this->whenCounted('presences'),
            'qr_code'         => new QrCodeResource($this->whenLoaded('qrCode')),
            'created_at'      => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
