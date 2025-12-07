<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presence extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'member_id',
        'date',
        'time',
        'status',
        'location_data',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'datetime:H:i:s',
        'signed_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'location_verified' => 'boolean',
        'location_data' => 'array'
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(member::class);
    }
}
