<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ec extends Model
{
    use HasFactory;
    protected $fillable = ['ue_id', 'code', 'intitule', 'volume_horaire', 'statut'];

    public function ue(): BelongsTo { return $this->belongsTo(Ue::class); }
    public function evenements(): HasMany { return $this->hasMany(Evenement::class); }

    /**
     * Étudiants inscrits à cet EC (CDC 7.2.3).
     * Table pivot : etudiant_ec
     */
    public function etudiants(): BelongsToMany
    {
        return $this->belongsToMany(Etudiant::class, 'etudiant_ec')
            ->withPivot('annee_id')
            ->withTimestamps();
    }

    /**
     * Récupère tous les ECs d'une filière et année donnée.
     */
    public static function forFiliereAndYear(int $filiereId, int $anneeId): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereHas('ue', function ($q) use ($filiereId, $anneeId) {
            $q->where('filiere_id', $filiereId)
              ->where('annee_id', $anneeId);
        })->get();
    }
}
