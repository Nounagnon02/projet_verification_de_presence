<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributs = $this->resource->getAttributes();

        return [
            'id'               => $this->id,
            'nom'              => $this->nom,
            'code'             => $this->code,
            'etablissement_id' => $this->etablissement_id,
            'etablissement'    => new EtablissementResource($this->whenLoaded('etablissement')),
            'latitude'         => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude'        => $this->longitude !== null ? (float) $this->longitude : null,
            'rayon_geofence_m' => $this->rayon_geofence_m,
            'ssid_attendu'     => $this->ssid_attendu,
            'bssid_attendu'    => $this->bssid_attendu,
            'ip_range'         => $this->ip_range,
            'hors_reseau'      => $this->hors_reseau,
            'actif'            => $this->actif,
            // Ce que la salle vérifie réellement au scan, calculé depuis ses
            // colonnes : jamais absent, contrairement aux colonnes elles-mêmes.
            'verifie_gps'      => $this->resource->verifieGps(),
            'verifie_wifi'     => $this->resource->verifieWifi(),
            // Alias (« evenements as seances_a_venir », « emploisDuTemps as
            // creneaux_count ») : whenCounted() ne les reconnaît pas.
            'seances_a_venir'  => $this->when(array_key_exists('seances_a_venir', $attributs), fn () => $this->seances_a_venir),
            'creneaux_count'   => $this->when(array_key_exists('creneaux_count', $attributs), fn () => $this->creneaux_count),
            'evenements'       => EvenementResource::collection($this->whenLoaded('evenements')),
            'created_at'       => $this->created_at?->format('Y-m-d'),
        ];
    }
}
