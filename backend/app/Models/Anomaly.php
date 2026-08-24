<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Anomaly extends Model
{
    protected $fillable = [
        'member_id',
        'etudiant_id',
        'type',
        'description',
        'severity',
        'metadata',
        'resolved',
        'resolved_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'resolved' => 'boolean',
        'resolved_at' => 'datetime'
    ];

    // Pas de relation vers « member » : la classe App\Models\Member n'existe pas.
    // La colonne member_id subsiste en base (migration
    // 2026_05_23_100003_create_anomalies_table) mais n'est plus alimentee ; toute
    // relation declaree ici leve une Error des qu'elle est chargee.

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }
}
