<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Année académique vue depuis l'espace d'un établissement
 * (Admin\AnneeAcademiqueController) : « active » et « close » sont relatifs à
 * CET établissement, pas à l'université entière — voir AnneeAcademiqueResource
 * pour la forme compacte partagée par EtudiantResource et UeResource.
 */
class AnneeAcademiqueEtablissementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributs = $this->resource->getAttributes();

        return [
            'id'         => $this->id,
            'libelle'    => $this->libelle,
            'date_debut' => $this->date_debut?->format('Y-m-d'),
            'date_fin'   => $this->date_fin?->format('Y-m-d'),
            // Posés par AnneeAcademiqueController::index() : absents sur un
            // show() isolé, qui ne les calcule pas.
            'en_cours_universite'  => $this->when(array_key_exists('en_cours_universite', $attributs), fn () => $this->en_cours_universite),
            'active'               => $this->when(array_key_exists('active', $attributs), fn () => $this->active),
            'statut'               => $this->when(array_key_exists('statut', $attributs), fn () => $this->statut),
            'close'                => $this->when(array_key_exists('close', $attributs), fn () => $this->close),
            'seances_a_venir_count' => $this->when(array_key_exists('seances_a_venir_count', $attributs), fn () => $this->seances_a_venir_count),
            'evenements_count'      => $this->whenCounted('evenements'),
            'ues_count'             => $this->whenCounted('ues'),
            'emplois_du_temps_count' => $this->whenCounted('emploisDuTemps'),
            'etudiants_count'       => $this->whenCounted('etudiants'),
        ];
    }
}
