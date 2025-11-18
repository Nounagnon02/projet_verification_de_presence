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
        'signature',
        'qr_code_id',
        'verification_method',
        'signed_at'
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'datetime:H:i:s',
        'signed_at' => 'datetime'
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(member::class);
    }
}
