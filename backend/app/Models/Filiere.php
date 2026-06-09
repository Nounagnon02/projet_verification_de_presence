<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Filiere extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'intitule', 'niveau', 'etablissement_id'];

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function etudiants(): HasMany
    {
        return $this->hasMany(Etudiant::class);
    }

    public function ues(): HasMany
    {
        return $this->hasMany(Ue::class);
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(Evenement::class);
    }

    public function scopeForEtablissement($query, ?int $etablissementId)
    {
        if ($etablissementId) {
            return $query->where('etablissement_id', $etablissementId);
        }
        return $query;
    }
}
