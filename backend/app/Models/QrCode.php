<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCode extends Model
{
    use HasFactory;

    protected $table = 'qrcodes';

    protected $fillable = [
        'evenement_id',
        'token',
        'expire_at',
        'actif',
    ];

    protected $casts = [
        'expire_at' => 'datetime',
        'actif' => 'boolean',
    ];

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class);
    }

    public function isExpired(): bool
    {
        return $this->expire_at->isPast();
    }
}