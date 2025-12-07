<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Anomaly extends Model
{
    protected $fillable = [
        'member_id',
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

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
