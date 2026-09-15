<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Filiere extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'intitule', 'niveau', 'etablissement_id', 'programme_id'];

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /** Programme dont cette filière est un niveau (IM pour IM-L2). */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function etudiants(): HasMany
    {
        return $this->hasMany(Etudiant::class);
    }

    /** UE que suit la filière : les siennes, et les cours communs. */
    public function ues(): BelongsToMany
    {
        return $this->belongsToMany(Ue::class, 'ue_filiere')->withTimestamps();
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(Evenement::class);
    }

    /**
     * Années académiques auxquelles cette filière est rattachée.
     */
    public function anneesAcademiques(): BelongsToMany
    {
        return $this->belongsToMany(AnneeAcademique::class, 'filiere_annee', 'filiere_id', 'annee_id')
            ->withTimestamps();
    }

    public function scopeForEtablissement($query, ?int $etablissementId)
    {
        if ($etablissementId) {
            return $query->where('etablissement_id', $etablissementId);
        }
        return $query;
    }

    /**
     * Filtre les filières rattachées à une année académique donnée.
     */
    /**
     * Filières rattachées à une année académique.
     *
     * On ne se fie PAS au seul pivot « filiere_annee ». Ce pivot n'est alimenté
     * qu'à la création d'une filière et par la reconduction : ni l'import
     * d'étudiants, ni StudentPromotionService ne le mettent à jour. Après une
     * promotion, il désigne encore l'année précédente.
     *
     * Une filière appartient donc à une année si elle y a du CONTENU — étudiants,
     * UEs ou événements — ou si le pivot le déclare. Ce dernier terme sert à la
     * filière fraîchement créée, encore vide, qu'il faut pouvoir choisir pour y
     * inscrire le premier étudiant.
     *
     * Définition unique, partagée par la liste des filtres et par la
     * reconduction : sans cela, l'écran affichait onze filières pour une année
     * dont la reconduction n'en reportait que dix.
     */
    public function scopeForAnnee($query, int $anneeId)
    {
        return $query->where(function ($q) use ($anneeId) {
            $q->whereHas('anneesAcademiques', fn ($p) => $p->where('annee_id', $anneeId))
              ->orWhereHas('etudiants', fn ($p) => $p->where('annee_id', $anneeId))
              ->orWhereHas('ues', fn ($p) => $p->where('annee_id', $anneeId))
              ->orWhereHas('evenements', fn ($p) => $p->where('annee_id', $anneeId));
        });
    }
}
