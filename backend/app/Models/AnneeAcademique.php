<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnneeAcademique extends Model
{
    use HasFactory;
    protected $table = 'annees_academiques';
    protected $fillable = ['libelle', 'date_debut', 'date_fin', 'active'];

    public function etudiants(): HasMany { return $this->hasMany(Etudiant::class, 'annee_id'); }
    public function evenements(): HasMany { return $this->hasMany(Evenement::class, 'annee_id'); }
}
