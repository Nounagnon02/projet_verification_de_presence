<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle pour les analyses IA (Gemini) asynchrones.
 * Conforme CDC 8.1 & 8.4 — Job queue dédié avec retry.
 *
 * @property int $id
 * @property string $type (schedule|courses)
 * @property string $status (pending|processing|completed|failed)
 * @property string|null $file_path
 * @property array|null $result
 * @property float|null $score_de_confiance
 * @property string|null $statut_analyse (valide|a_reverifier)
 * @property string|null $warning
 * @property string|null $error_message
 * @property int|null $user_id
 */
class Analyse extends Model
{
    protected $fillable = [
        'type',
        'status',
        'file_path',
        'result',
        'score_de_confiance',
        'statut_analyse',
        'warning',
        'error_message',
        'user_id',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'result' => 'array',
        'score_de_confiance' => 'float',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
