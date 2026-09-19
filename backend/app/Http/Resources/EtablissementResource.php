<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EtablissementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributs = $this->resource->getAttributes();

        return [
            'id'              => $this->id,
            'code'            => $this->code,
            'nom'             => $this->nom,
            'email'           => $this->email,
            'telephone'       => $this->telephone,
            'adresse'         => $this->adresse,
            'logo'            => $this->logo,
            'actif'           => $this->actif,
            'annee_active_id' => $this->annee_active_id,
            'annee_active'    => new AnneeAcademiqueResource($this->whenLoaded('anneeActive')),
            'filieres_count'  => $this->whenCounted('filieres'),
            'users_count'     => $this->whenCounted('users'),
            // Posés par EtablissementController::show()/stats() : absents ailleurs.
            'total_etudiants' => $this->when(array_key_exists('total_etudiants', $attributs), fn () => $this->total_etudiants),
            'total_presences' => $this->when(array_key_exists('total_presences', $attributs), fn () => $this->total_presences),
            'created_at'      => $this->created_at?->format('Y-m-d'),
        ];
    }
}
