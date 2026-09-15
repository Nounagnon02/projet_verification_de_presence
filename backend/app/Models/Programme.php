<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Programme d'études d'un établissement (IM, MIAGE, GL…).
 *
 * Une filière est ce programme à un niveau : IM-L1, IM-L2 et IM-L3 sont les
 * filières du programme IM.
 */
class Programme extends Model
{
    protected $fillable = ['etablissement_id', 'code', 'intitule'];

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function filieres(): HasMany
    {
        return $this->hasMany(Filiere::class);
    }

    /**
     * Programme que désigne une filière : son code privé du niveau
     * (« IM-L2 » → IM), son intitulé sans le « (L2) » final.
     *
     * @return array{0: string, 1: string} code et intitulé
     */
    public static function deduire(string $codeFiliere, string $intituleFiliere, string $niveau): array
    {
        $n = preg_quote($niveau, '/');

        $code = preg_replace("/[-_ ]?{$n}$/i", '', $codeFiliere) ?? '';
        $code = $code !== '' ? $code : $codeFiliere;

        $intitule = preg_replace("/\s*\(\s*{$n}\s*\)\s*$/iu", '', $intituleFiliere) ?? '';
        $intitule = trim(preg_replace("/\s*[—–-]\s*{$n}\b/u", '', $intitule) ?? '');

        return [mb_strtoupper(mb_substr($code, 0, 20)), $intitule !== '' ? $intitule : $intituleFiliere];
    }
}
