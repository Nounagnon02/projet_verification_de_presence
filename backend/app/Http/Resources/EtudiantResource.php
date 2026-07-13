<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EtudiantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'matricule' => $this->matricule,
            'email' => $this->email,
            'identifiant_unique' => $this->identifiant_unique,
            'filiere' => new \App\Http\Resources\FiliereResource($this->whenLoaded('filiere')),
            'annee' => new \App\Http\Resources\AnneeAcademiqueResource($this->whenLoaded('anneeAcademique')),
            'created_at' => $this->created_at,
        ];
    }
}
