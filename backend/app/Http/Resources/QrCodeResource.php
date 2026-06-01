<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QrCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'token'      => $this->token,
            'expire_at'  => $this->expire_at?->format('Y-m-d H:i'),
            'actif'      => $this->actif,
            'is_expired' => $this->isExpired(),
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
