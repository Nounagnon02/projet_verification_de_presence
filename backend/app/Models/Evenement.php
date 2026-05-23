<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Evenement extends Model
{
    use HasFactory;

    protected $fillable = [
        'ec_id',
        'filiere_id',
        'annee_id',
        'date',
        'heure_debut',
        'heure_fin',
        'salle',
        'statut',
    ];

    protected $casts = [
        'date'        => 'date',
        'heure_debut'  => 'string',
        'heure_fin'   => 'string',
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

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    public function qrCode(): HasOne
    {
        return $this->hasOne(QrCode::class)->where('actif', true);
    }
}
