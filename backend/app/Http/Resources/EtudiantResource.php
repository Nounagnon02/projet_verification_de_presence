<?php

declare(strict_types=1);

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
            'est_responsable' => (bool) $this->est_responsable,
            'filiere' => new \App\Http\Resources\FiliereResource($this->whenLoaded('filiere')),
            'annee' => new \App\Http\Resources\AnneeAcademiqueResource($this->whenLoaded('anneeAcademique')),
            // Groupes de TD et de TP de son année.
            'groupes' => $this->whenLoaded('groupes', fn () => $this->groupes
                ->filter(fn ($g) => (int) $g->pivot->annee_id === (int) $this->annee_id)
                ->map(fn ($g) => ['id' => $g->id, 'libelle' => $g->libelle, 'type' => $g->type])
                ->values()),
            'created_at' => $this->created_at,
        ];
    }
}
