<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presence extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'date',
        'time',
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'datetime:H:i:s',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(member::class);
    }
}
