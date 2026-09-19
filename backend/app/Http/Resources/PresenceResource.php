<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributs = $this->resource->getAttributes();

        return [
            'id'                => $this->id,
            'etudiant_id'       => $this->etudiant_id,
            'evenement_id'      => $this->evenement_id,
            'etudiant'          => new EtudiantResource($this->whenLoaded('etudiant')),
            'evenement'         => new EvenementResource($this->whenLoaded('evenement')),
            'heure_scan'        => $this->heure_scan?->toIso8601String(),
            'statut'            => $this->statut,
            'device_fingerprint' => $this->device_fingerprint,
            'ip_address'        => $this->ip_address,
            'latitude'          => $this->latitude,
            'longitude'         => $this->longitude,
            'validated_by'      => $this->validated_by,
            'validated_at'      => $this->validated_at?->toIso8601String(),
            'validation_motif'  => $this->validation_motif,
            // Posé par PresenceController::joindreMemeAppareil() : absent hors
            // de la file d'arbitrage.
            'meme_appareil'     => $this->when(array_key_exists('meme_appareil', $attributs), fn () => $this->meme_appareil),
            'created_at'        => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
