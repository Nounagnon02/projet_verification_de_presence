<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Fermeture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FermetureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'annee_id'         => $this->annee_id,
            'etablissement_id' => $this->etablissement_id,
            'type'             => $this->type,
            'type_libelle'     => Fermeture::LIBELLES[$this->type] ?? $this->type,
            'libelle'          => $this->libelle,
            'date_debut'       => $this->date_debut?->format('Y-m-d'),
            'date_fin'         => $this->date_fin?->format('Y-m-d'),
            // Sans établissement : jour férié de l'université, déclaré par le
            // super administrateur. Avec : fermeture propre à cet établissement.
            'portee'           => $this->etablissement_id === null ? 'universite' : 'faculte',
            'created_at'       => $this->created_at?->format('Y-m-d'),
        ];
    }
}
