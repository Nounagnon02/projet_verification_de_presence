<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Année académique de l'université (2025-2026…).
 *
 * Les années sont communes à tous les établissements et créées par le super
 * administrateur. « active » désigne l'année en cours de l'université ; chaque
 * établissement choisit la sienne (etablissements.annee_active_id), parce que
 * les facultés ne basculent pas toutes le même jour. activePour() tranche.
 *
 * etablissement_id n'est plus renseigné : il datait du temps où chaque faculté
 * créait ses propres années.
 */
class AnneeAcademique extends Model
{
    use HasFactory;
    protected $table = 'annees_academiques';
    protected $fillable = ['libelle', 'date_debut', 'date_fin', 'active', 'etablissement_id'];

    protected $casts = [
        // Des dates, pas des instants. En « datetime », le 1er octobre à minuit
        // (heure du Bénin) sortait « 2025-09-30T23:00:00Z » : le formulaire ne
        // le lisait pas, et une modification le renvoyait tel quel — l'année
        // reculait d'un jour à chaque enregistrement.
        'date_debut' => 'date:Y-m-d',
        'date_fin'   => 'date:Y-m-d',
        'active'     => 'boolean',
    ];

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function etudiants(): HasMany { return $this->hasMany(Etudiant::class, 'annee_id'); }
    public function evenements(): HasMany { return $this->hasMany(Evenement::class, 'annee_id'); }
    public function ues(): HasMany { return $this->hasMany(Ue::class, 'annee_id'); }
    public function emploisDuTemps(): HasMany { return $this->hasMany(EmploiDuTemps::class, 'annee_id'); }

    /**
     * Filières rattachées à cette année académique.
     */
    public function filieres(): BelongsToMany
    {
        return $this->belongsToMany(Filiere::class, 'filiere_annee', 'annee_id', 'filiere_id')
            ->withTimestamps();
    }

    /**
     * Année sur laquelle travaille un établissement : celle qu'il a choisie,
     * sinon l'année en cours de l'université.
     */
    public static function activePour(?int $etablissementId): ?self
    {
        $choisie = $etablissementId
            ? Etablissement::whereKey($etablissementId)->value('annee_active_id')
            : null;

        return ($choisie ? static::find($choisie) : null)
            ?? static::where('active', true)->first();
    }

    /**
     * Close pour un établissement : elle commence avant son année active. Sa
     * structure (UE, EC, emploi du temps, séances, inscriptions) ne se modifie
     * plus ; ses présences restent corrigeables. Pour y corriger autre chose,
     * l'établissement repasse temporairement dessus.
     */
    public function estClosePour(?int $etablissementId): bool
    {
        $active = static::activePour($etablissementId);

        return $active !== null && $this->date_debut !== null && $this->date_debut->lt($active->date_debut);
    }

    /**
     * Ajoute etudiants_count : les étudiants inscrits dans l'année aujourd'hui,
     * et ceux qui y ont suivi des EC avant d'être promus. Compter
     * etudiants.annee_id seul vidait une année dès la promotion de ses
     * étudiants, alors que ses présences et ses taux restent.
     */
    public function scopeAvecEffectifs(\Illuminate\Database\Eloquent\Builder $query, ?int $etablissementId = null): \Illuminate\Database\Eloquent\Builder
    {
        $filtre = $etablissementId ? ' and s.filiere_id in (select id from filieres where etablissement_id = ?)' : '';

        if (is_null($query->getQuery()->columns)) {
            $query->select('annees_academiques.*');
        }

        return $query->selectRaw(
            "(select count(*) from (
                select s.id from etudiants s
                 where s.annee_id = annees_academiques.id and s.deleted_at is null{$filtre}
                union
                select ee.etudiant_id from etudiant_ec ee join etudiants s on s.id = ee.etudiant_id
                 where ee.annee_id = annees_academiques.id and s.deleted_at is null{$filtre}
            ) inscrits) as etudiants_count",
            $etablissementId ? [$etablissementId, $etablissementId] : []
        );
    }

    /** « terminee », « en_cours » ou « a_venir », au regard d'aujourd'hui. */
    public function statut(): string
    {
        $aujourdhui = today();

        return match (true) {
            (bool) $this->date_fin?->lt($aujourdhui)   => 'terminee',
            (bool) $this->date_debut?->gt($aujourdhui) => 'a_venir',
            default                                     => 'en_cours',
        };
    }
}
