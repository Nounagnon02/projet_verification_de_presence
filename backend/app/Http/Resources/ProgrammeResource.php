<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgrammeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'etablissement_id' => $this->etablissement_id,
            'code'             => $this->code,
            'intitule'         => $this->intitule,
            'filieres_count'   => $this->whenCounted('filieres'),
        ];
    }
}
