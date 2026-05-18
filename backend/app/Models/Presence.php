<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presence extends Model
{
    use HasFactory;

    protected $fillable = [
        'etudiant_id',
        'evenement_id',
        'heure_scan',
        'device_fingerprint',
        'ip_address',
        'statut',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'heure_scan' => 'datetime',
    ];

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class);
    }
}
