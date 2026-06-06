<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnneeAcademiqueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'annee'       => $this->libelle,
            'libelle'     => $this->libelle,
            'date_debut'  => $this->date_debut?->format('Y-m-d'),
            'date_fin'    => $this->date_fin?->format('Y-m-d'),
            'is_active'   => $this->active,
            'created_at'  => $this->created_at?->format('Y-m-d'),
        ];
    }
}
