<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Etablissement extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'nom', 'email', 'telephone', 'adresse', 'logo', 'actif', 'annee_active_id',
    ];

    /**
     * Année sur laquelle travaille l'établissement. Vide : il suit l'année en
     * cours de l'université (AnneeAcademique::activePour).
     */
    public function anneeActive(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class, 'annee_active_id');
    }

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function filieres(): HasMany
    {
        return $this->hasMany(Filiere::class);
    }

    public function anneesAcademiques(): HasMany
    {
        return $this->hasMany(AnneeAcademique::class);
    }
}
