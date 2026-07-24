<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Etudiant extends Model
{
    use HasFactory, HasUuids, HasApiTokens;

    protected $fillable = [
        'nom',
        'prenom',
        'matricule',
        'filiere_id',
        'annee_id',
        'email',
        'identifiant_unique',
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
        $ecs = Ec::forFiliereAndYear($this->filiere_id, $this->annee_id);

        foreach ($ecs as $ec) {
            $this->ecs()->syncWithoutDetaching([
                $ec->id => ['annee_id' => $this->annee_id],
            ]);
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
