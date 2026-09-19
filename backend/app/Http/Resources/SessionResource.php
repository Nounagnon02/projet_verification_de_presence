<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un token Sanctum, vu comme une « session » d'appareil : jamais son hash, ni
 * ses capacités — seulement de quoi le reconnaître et le révoquer.
 */
class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributs = $this->resource->getAttributes();

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'is_current'  => $this->when(array_key_exists('is_current', $attributs), fn () => $this->is_current),
            'last_active' => $this->last_used_at ? $this->last_used_at->diffForHumans() : 'jamais utilisé',
            'created_at'  => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
