<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Groupe de TD ou de TP d'une promotion (filière et année) : G1, G2…
 *
 * Une séance de TD ou de TP peut viser un groupe : seuls ses membres y sont
 * attendus, et chaque groupe reçoit la totalité du volume de TD ou de TP de
 * l'EC. Un étudiant appartient à un groupe de TD et à un groupe de TP au plus,
 * pour une année (etudiant_groupe, unique par étudiant, année et type).
 */
class Groupe extends Model
{
    public const TYPES = ['td', 'tp'];

    protected $fillable = ['filiere_id', 'annee_id', 'type', 'libelle'];

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    public function annee(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class, 'annee_id');
    }

    public function etudiants(): BelongsToMany
    {
        return $this->belongsToMany(Etudiant::class, 'etudiant_groupe')->withPivot(['annee_id', 'type'])->withTimestamps();
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(Evenement::class);
    }

    public function creneaux(): HasMany
    {
        return $this->hasMany(EmploiDuTemps::class);
    }
}
