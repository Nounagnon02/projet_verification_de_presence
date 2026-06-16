<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Salle extends Model
{
    use HasFactory;

    protected $fillable = [
        'etablissement_id',
        'nom',
        'code',
        'latitude',
        'longitude',
        'rayon_geofence_m',
        'ssid_attendu',
        'bssid_attendu',
        'ip_range',
        'hors_reseau',
        'actif',
    ];

    protected $casts = [
        'latitude'         => 'decimal:8',
        'longitude'        => 'decimal:8',
        'rayon_geofence_m' => 'integer',
        'hors_reseau'      => 'boolean',
        'actif'            => 'boolean',
    ];

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(Evenement::class);
    }

    /**
     * Calcule la distance en mètres entre la salle et une position GPS donnée.
     * Utilise la formule de Haversine.
     */
    public function distanceMetres(?float $latitude, ?float $longitude): ?float
    {
        if ($this->latitude === null || $this->longitude === null || $latitude === null || $longitude === null) {
            return null;
        }

        $earthRadius = 6371000; // mètres

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo   = deg2rad($latitude);
        $lonTo   = deg2rad($longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Vérifie si une position GPS est dans le rayon de geofence de la salle.
     */
    public function isWithinGeofence(?float $latitude, ?float $longitude): bool
    {
        $distance = $this->distanceMetres($latitude, $longitude);
        if ($distance === null) {
            return false;
        }
        return $distance <= $this->rayon_geofence_m;
    }

    /**
     * Vérifie si le réseau WiFi correspond à la configuration de la salle.
     */
    public function matchesWifi(?string $ssid, ?string $bssid): bool
    {
        // Si la salle est marquée hors réseau, on skip la vérif WiFi
        if ($this->hors_reseau) {
            return true;
        }

        // Si aucun SSID/BSSID configuré, on skip
        if (!$this->ssid_attendu && !$this->bssid_attendu) {
            return true;
        }

        // Vérification SSID
        if ($this->ssid_attendu && $ssid) {
            if (mb_strtolower(trim($ssid)) === mb_strtolower(trim($this->ssid_attendu))) {
                return true;
            }
        }

        // Vérification BSSID
        if ($this->bssid_attendu && $bssid) {
            if (mb_strtolower(trim($bssid)) === mb_strtolower(trim($this->bssid_attendu))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si l'IP client est dans la plage configurée.
     */
    public function matchesIpRange(?string $ip): bool
    {
        if (!$this->ip_range || !$ip) {
            return true; // pas de restriction IP configurée
        }

        // Support notation CIDR (ex: 192.168.1.0/24)
        if (str_contains($this->ip_range, '/')) {
            [$subnet, $mask] = explode('/', $this->ip_range);
            $mask = (int) $mask;

            $ipLong    = ip2long($ip);
            $subnetLong = ip2long($subnet);

            if ($ipLong === false || $subnetLong === false) {
                return false;
            }

            $netmask = -1 << (32 - $mask);
            return ($ipLong & $netmask) === ($subnetLong & $netmask);
        }

        // Support plage simple (ex: 192.168.1.100)
        return $ip === $this->ip_range;
    }
}
