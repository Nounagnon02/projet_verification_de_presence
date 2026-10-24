<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $scan_minutes_avant_fin
 * @property int $scan_minutes_apres_fin
 * @property int $qr_ttl_secondes
 * @property int $qr_visible_delegue_avant_fin
 * @property int $seance_duree_max_heures
 * @property int $limite_scan_par_etudiant
 * @property int $limite_scan_par_ip
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ParametreGlobalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'scan_minutes_avant_fin' => $this->scan_minutes_avant_fin,
            'scan_minutes_apres_fin' => $this->scan_minutes_apres_fin,
            'qr_ttl_secondes' => $this->qr_ttl_secondes,
            'qr_visible_delegue_avant_fin' => $this->qr_visible_delegue_avant_fin,
            'seance_duree_max_heures' => $this->seance_duree_max_heures,
            'limite_scan_par_etudiant' => $this->limite_scan_par_etudiant,
            'limite_scan_par_ip' => $this->limite_scan_par_ip,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
