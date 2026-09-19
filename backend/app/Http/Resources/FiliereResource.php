<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiliereResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributs = $this->resource->getAttributes();

        return [
            'id'             => $this->id,
            'code'           => $this->code,
            'intitule'       => $this->intitule,
            'niveau'         => $this->niveau,
            'programme_id'      => $this->programme_id,
            'etablissement_id'  => $this->etablissement_id,
            'programme'      => $this->whenLoaded('programme', fn () => ['id' => $this->programme->id, 'code' => $this->programme->code, 'intitule' => $this->programme->intitule]),
            'etudiants_count' => $this->whenCounted('etudiants'),
            'ues_count'      => $this->whenCounted('ues'),
            'ues'            => UeResource::collection($this->whenLoaded('ues')),
            // Comptes « toutes années confondues » (alias withCount) et
            // répartition par semestre : posés par FiliereController::index,
            // absents ailleurs.
            'etudiants_total'   => $this->when(array_key_exists('etudiants_total', $attributs), fn () => $this->etudiants_total),
            'ues_total'         => $this->when(array_key_exists('ues_total', $attributs), fn () => $this->ues_total),
            'evenements_total'  => $this->when(array_key_exists('evenements_total', $attributs), fn () => $this->evenements_total),
            'semestres'         => $this->when(array_key_exists('semestres', $attributs), fn () => $this->semestres),
            // (object) : un tableau à clés numériques (semestre => n) imbriqué
            // dans une ressource est réindexé en liste séquentielle par
            // removeMissingValues() — un objet JSON échappe à ce nettoyage.
            'ues_par_semestre'  => $this->when(array_key_exists('ues_par_semestre', $attributs), fn () => (object) $this->ues_par_semestre),
            'created_at'     => $this->created_at?->format('Y-m-d'),
        ];
    }
}
