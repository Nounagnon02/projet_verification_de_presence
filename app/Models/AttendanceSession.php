<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    protected $fillable = [
        'group_id',
        'event_name',
        'event_date',
        'opened_by',
        'opened_at',
        'closed_at',
        'is_active',
        'latitude',
        'longitude',
        'radius',
        'location_name',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'event_date' => 'date',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'radius' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }
}
