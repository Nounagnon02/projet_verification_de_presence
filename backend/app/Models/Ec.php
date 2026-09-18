<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ec extends Model
{
    use HasFactory;
    protected $fillable = ['ue_id', 'code', 'intitule', 'volume_horaire', 'volume_cm', 'volume_td', 'volume_tp', 'volume_td_tp', 'volume_a_ventiler'];

    protected $casts = [
        'volume_horaire'    => 'integer',
        'volume_cm'         => 'integer',
        'volume_td'         => 'integer',
        'volume_tp'         => 'integer',
        'volume_td_tp'      => 'integer',
        'volume_a_ventiler' => 'boolean',
    ];

    public function ue(): BelongsTo { return $this->belongsTo(Ue::class); }

    /** L'année et l'établissement de l'UE, recopiés pour l'unicité des codes par année. */
    /**
     * Avancement calculé à la lecture (AvancementCours) : les listes le posent
     * en une requête ; un EC isolé le calcule à la demande.
     */
    protected function statut(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::get(
            fn ($value) => $value ?? app(\App\Services\AvancementCours::class)->statutDe($this)
        );
    }

    protected static function booted(): void
    {
        // Le statut et les heures faites se calculent : ils ne s'écrivent jamais.
        static::saving(function ($cours) {
            $cours->offsetUnset('statut');
            $cours->offsetUnset('heures_faites');
        });

        static::saving(function (Ec $ec) {
            if ($ec->isDirty('ue_id') || $ec->annee_id === null) {
                $ue = Ue::find($ec->ue_id);
                $ec->annee_id = $ue?->annee_id;
                $ec->etablissement_id = $ue?->etablissement_id;
            }

            // Volumes par type : le total en découle, et l'EC n'est plus « à
            // ventiler ». Sans volume par type (ancien import, EC d'avant), le
            // total saisi reste la seule référence.
            $parType = (int) $ec->volume_cm + (int) $ec->volume_td + (int) $ec->volume_tp + (int) $ec->volume_td_tp;

            if ($parType > 0) {
                $ec->volume_horaire = $parType;
                $ec->volume_a_ventiler = false;
            } elseif (!$ec->exists) {
                $ec->volume_a_ventiler = true;
            }
        });
    }
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
        // Les UE que suit la filière cette année, cours communs compris.
        return static::whereHas('ue', function ($q) use ($filiereId, $anneeId) {
            $q->where('annee_id', $anneeId)
              ->whereHas('filieres', fn ($f) => $f->where('filieres.id', $filiereId));
        })->get();
    }
}
