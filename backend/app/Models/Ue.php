<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ue extends Model
{
    use HasFactory;
    protected $fillable = ['code', 'intitule', 'filiere_id', 'annee_id', 'semestre', 'volume_horaire', 'credits'];

    public function filiere(): BelongsTo { return $this->belongsTo(Filiere::class); }
    public function ecs(): HasMany { return $this->hasMany(Ec::class); }

    /**
     * Filières qui suivent l'UE, porteuse comprise : plusieurs pour un cours
     * commun. filiere_id reste la filière porteuse.
     */
    public function filieres(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Filiere::class, 'ue_filiere')->withTimestamps();
    }

    /**
     * L'établissement suit la filière, et les EC suivent l'UE : la base s'en
     * sert pour qu'un code ne soit porté qu'une fois par année et par
     * établissement (RegistreMaquette).
     */
    /**
     * Avancement calculé à la lecture (AvancementCours) : les listes le posent
     * en une requête ; une UE isolée le calcule à la demande.
     */
    protected function statut(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::get(
            fn ($value) => $value ?? app(\App\Services\AvancementCours::class)->statutDeUe($this)
        );
    }

    protected static function booted(): void
    {
        // Le statut et les heures faites se calculent : ils ne s'écrivent jamais.
        static::saving(fn ($cours) => $cours->offsetUnset('statut'));

        static::saving(function (Ue $ue) {
            if ($ue->isDirty('filiere_id') || $ue->etablissement_id === null) {
                $ue->etablissement_id = Filiere::whereKey($ue->filiere_id)->value('etablissement_id');
            }
        });

        static::saved(function (Ue $ue) {
            // La porteuse suit toujours son UE.
            if ($ue->wasRecentlyCreated || $ue->wasChanged('filiere_id')) {
                if ($ue->wasChanged('filiere_id') && $ue->getOriginal('filiere_id')) {
                    $ue->filieres()->detach($ue->getOriginal('filiere_id'));
                }
                $ue->filieres()->syncWithoutDetaching([$ue->filiere_id]);
            }

            if ($ue->wasChanged(['annee_id', 'etablissement_id'])) {
                Ec::where('ue_id', $ue->id)->update(['annee_id' => $ue->annee_id, 'etablissement_id' => $ue->etablissement_id]);
            }
        });
    }
}
