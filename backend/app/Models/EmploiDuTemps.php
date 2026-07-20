<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploiDuTemps extends Model
{
    protected $table = 'emploi_du_temps';

    protected $fillable = [
        'ec_id',
        'filiere_id',
        'annee_id',
        'jour_semaine',
        'heure_debut',
        'heure_fin',
        'salle_id',
        'salle_libelle',
        'type_cours',
    ];

    protected $casts = [
        'jour_semaine' => 'integer',
    ];

    public const JOURS = [
        1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi',
        5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche',
    ];

    public function ec(): BelongsTo
    {
        return $this->belongsTo(Ec::class);
    }

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    public function anneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class, 'annee_id');
    }

    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }

    public function getJourLibelleAttribute(): string
    {
        return self::JOURS[$this->jour_semaine] ?? 'Inconnu';
    }
}
