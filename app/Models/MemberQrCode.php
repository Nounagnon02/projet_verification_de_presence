<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MemberQrCode extends Model
{
    protected $fillable = [
        'member_id',
        'token',
        'is_active',
        'revoked_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'revoked_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public static function generateToken(): string
    {
        return Str::random(40);
    }
}
