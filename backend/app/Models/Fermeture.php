<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jours sans cours. Sans établissement, l'université entière : les jours
 * fériés, que seul le super administrateur déclare. Chaque établissement
 * déclare ses vacances, ses examens et ses autres fermetures.
 */
class Fermeture extends Model
{
    public const TYPES = ['ferie', 'vacances', 'examens', 'autre'];

    /** Ce qu'un établissement déclare lui-même. */
    public const TYPES_FACULTE = ['vacances', 'examens', 'autre'];

    public const LIBELLES = [
        'ferie'    => 'Jour férié',
        'vacances' => 'Vacances',
        'examens'  => 'Examens',
        'autre'    => 'Autre fermeture',
    ];

    protected $fillable = ['etablissement_id', 'annee_id', 'type', 'libelle', 'date_debut', 'date_fin'];

    protected $casts = [
        'date_debut' => 'date:Y-m-d',
        'date_fin'   => 'date:Y-m-d',
    ];

    public function annee(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class, 'annee_id');
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }
}
