<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Filiere extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'intitule', 'niveau'];

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
}
