<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Période de cours des semestres impairs (S1, S3…) ou pairs (S2, S4…) d'un
 * établissement pour une année. Hors de cette période, aucune séance de ces
 * semestres n'est générée depuis l'emploi du temps.
 */
class PeriodeSemestre extends Model
{
    public const PARITES = ['impair', 'pair'];

    public const LIBELLES = [
        'impair' => 'Semestres impairs (S1, S3, S5…)',
        'pair'   => 'Semestres pairs (S2, S4, S6…)',
    ];

    protected $table = 'periodes_semestre';

    protected $fillable = ['etablissement_id', 'annee_id', 'parite', 'date_debut', 'date_fin'];

    protected $casts = [
        'date_debut' => 'date:Y-m-d',
        'date_fin'   => 'date:Y-m-d',
    ];

    public static function pariteDe(int $semestre): string
    {
        return $semestre % 2 === 1 ? 'impair' : 'pair';
    }

    public function annee(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class, 'annee_id');
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }
}
