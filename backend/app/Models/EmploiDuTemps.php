<?php

declare(strict_types=1);

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
        'groupe_id',
        'valide_du',
        'valide_au',
        'enseignant',
    ];

    protected $casts = [
        'jour_semaine' => 'integer',
        'valide_du'    => 'date:Y-m-d',
        'valide_au'    => 'date:Y-m-d',
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

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class);
    }

    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }

    /** Le créneau vaut-il ce jour-là ? Un emploi du temps a des versions successives. */
    public function valableLe(\Carbon\Carbon $date): bool
    {
        $jour = $date->toDateString();

        return ($this->valide_du === null || $this->valide_du->toDateString() <= $jour)
            && ($this->valide_au === null || $this->valide_au->toDateString() >= $jour);
    }

    public function getJourLibelleAttribute(): string
    {
        return self::JOURS[$this->jour_semaine] ?? 'Inconnu';
    }
}
