<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class Etudiant extends Model
{
    use HasFactory, HasUuids, HasApiTokens, SoftDeletes;

    protected $fillable = [
        'nom',
        'prenom',
        'matricule',
        'filiere_id',
        'annee_id',
        'email',
        'identifiant_unique',
        'est_responsable',
    ];

    protected $casts = [
        'est_responsable' => 'boolean',
    ];

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    public function anneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class, 'annee_id');
    }

    /**
     * ECs auxquels l'étudiant est inscrit (CDC 7.2.3).
     * Table pivot : etudiant_ec
     */
    public function ecs(): BelongsToMany
    {
        return $this->belongsToMany(Ec::class, 'etudiant_ec')
            ->withPivot('annee_id')
            ->withTimestamps();
    }

    /**
     * Inscrit l'étudiant à tous les ECs de sa filière et année (CDC 7.2.3).
     */
    public function autoEnroll(): void
    {
        $ecIds = Ec::forFiliereAndYear($this->filiere_id, $this->annee_id)->modelKeys();

        if ($ecIds === []) {
            return;
        }

        // Un aller-retour pour lire l'état du pivot, puis un seul INSERT pour
        // toutes les inscriptions manquantes. La boucle précédente appelait
        // syncWithoutDetaching par EC, soit 2 requêtes chacun : ~25 allers-retours
        // pour une filière de 12 ECs, ce qui représentait l'essentiel des ~7 s
        // d'une inscription en production (base Supabase en Irlande,
        // application à Render/Oregon).
        $actuels = $this->ecs()->get(['ecs.id'])->keyBy('id');

        $manquants = array_values(array_filter($ecIds, fn ($id) => !$actuels->has($id)));

        if ($manquants !== []) {
            $this->ecs()->attach(array_fill_keys($manquants, ['annee_id' => $this->annee_id]));
        }

        // Une inscription déjà présente mais rattachée à une autre année est
        // recalée sur l'année courante — c'est ce que faisait syncWithoutDetaching.
        // En pratique la boucle ne déclenche aucune requête.
        foreach ($actuels as $ec) {
            if ((int) $ec->pivot->annee_id !== (int) $this->annee_id) {
                $this->ecs()->updateExistingPivot($ec->id, ['annee_id' => $this->annee_id]);
            }
        }
    }

    /**
     * Recalcule les inscriptions aux ECs.
     * Supprime toutes les inscriptions existantes et ré-inscrit
     * l'étudiant aux ECs de sa filière et année actuelles.
     * Utile lors d'un changement de filière ou d'année.
     */
    public function recalculateEnrollments(): void
    {
        $this->ecs()->detach();
        $this->autoEnroll();
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }
}
