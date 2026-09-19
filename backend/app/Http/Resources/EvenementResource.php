<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvenementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'ec_id'           => $this->ec_id,
            'filiere_id'      => $this->filiere_id,
            'salle_id'        => $this->salle_id,
            'groupe_id'       => $this->groupe_id,
            'annee_id'        => $this->annee_id,
            'type_cours'      => $this->type_cours,
            'date'            => $this->date?->format('Y-m-d'),
            'heure_debut'     => $this->heure_debut,
            'heure_fin'       => $this->heure_fin,
            'salle'           => $this->salle,
            'statut'          => $this->statut,
            'ec'              => new EcResource($this->whenLoaded('ec')),
            'filiere'         => new FiliereResource($this->whenLoaded('filiere')),
            'presences_count' => $this->whenCounted('presences'),
            'presences'       => PresenceResource::collection($this->whenLoaded('presences')),
            'qr_code'         => new QrCodeResource($this->whenLoaded('qrCode')),
            'created_at'      => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
