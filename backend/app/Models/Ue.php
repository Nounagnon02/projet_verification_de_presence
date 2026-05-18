<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ue extends Model
{
    use HasFactory;
    protected $fillable = ['code', 'intitule', 'filiere_id'];

    public function filiere(): BelongsTo { return $this->belongsTo(Filiere::class); }
    public function ecs(): HasMany { return $this->hasMany(Ec::class); }
}
