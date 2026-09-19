<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributs = $this->resource->getAttributes();

        return [
            'id'          => $this->id,
            'filiere_id'  => $this->filiere_id,
            'annee_id'    => $this->annee_id,
            'type'        => $this->type,
            'libelle'     => $this->libelle,
            'filiere'     => new FiliereResource($this->whenLoaded('filiere')),
            'etudiants_count'    => $this->whenCounted('etudiants'),
            // Alias (« etudiants as responsables_count ») : whenCounted() ne
            // le reconnaît pas, seul « etudiants_count » suit sa convention.
            'responsables_count' => $this->when(array_key_exists('responsables_count', $attributs), fn () => $this->responsables_count),
        ];
    }
}
