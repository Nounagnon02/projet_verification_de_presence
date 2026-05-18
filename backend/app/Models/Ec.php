<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ec extends Model
{
    use HasFactory;
    protected $fillable = ['ue_id', 'code', 'intitule', 'volume_horaire'];

    public function ue(): BelongsTo { return $this->belongsTo(Ue::class); }
    public function evenements(): HasMany { return $this->hasMany(Evenement::class); }
}
