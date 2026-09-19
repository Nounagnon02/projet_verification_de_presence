<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Année académique vue depuis l'espace super administrateur
 * (SuperAdmin\AnneeUniversitaireController) : « active » désigne l'année en
 * cours de l'université, sans relativité à un établissement.
 */
class AnneeUniversitaireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributs = $this->resource->getAttributes();

        return [
            'id'         => $this->id,
            'libelle'    => $this->libelle,
            'date_debut' => $this->date_debut?->format('Y-m-d'),
            'date_fin'   => $this->date_fin?->format('Y-m-d'),
            'active'     => $this->active,
            'statut'              => $this->when(array_key_exists('statut', $attributs), fn () => $this->statut),
            'etablissements'      => $this->when(array_key_exists('etablissements', $attributs), fn () => $this->etablissements),
            'seances_a_venir_count' => $this->when(array_key_exists('seances_a_venir_count', $attributs), fn () => $this->seances_a_venir_count),
            'seances_retirees'    => $this->when(array_key_exists('seances_retirees', $attributs), fn () => $this->seances_retirees),
            'evenements_count'      => $this->whenCounted('evenements'),
            'ues_count'             => $this->whenCounted('ues'),
            'emplois_du_temps_count' => $this->whenCounted('emploisDuTemps'),
            'etudiants_count'       => $this->whenCounted('etudiants'),
        ];
    }
}
