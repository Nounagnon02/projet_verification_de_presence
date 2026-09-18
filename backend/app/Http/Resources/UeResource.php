<?php

declare(strict_types=1);

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
            // Toutes les filières qui la suivent : plusieurs pour un cours commun.
            'filieres'       => $this->whenLoaded('filieres', fn () => $this->filieres->map(fn ($f) => ['id' => $f->id, 'code' => $f->code, 'intitule' => $f->intitule])->values()),
            // L'année, pour qu'un écran sache si l'UE appartient à une année close.
            'annee_id'       => $this->annee_id,
            'credits'        => $this->credits,
            'annee'          => new AnneeAcademiqueResource($this->whenLoaded('annee')),
            'ecs'            => EcResource::collection($this->whenLoaded('ecs')),
            'ecs_count'      => $this->whenCounted('ecs'),
            'created_at'     => $this->created_at?->format('Y-m-d'),
        ];
    }
}
