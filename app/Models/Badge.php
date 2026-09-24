<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Badge extends Model
{
    protected $fillable = [
        'name',
        'icon',
        'description',
        'condition',
        'threshold',
        'points',
        'color',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'points' => 'integer',
        'threshold' => 'integer'
    ];

    /**
     * Les membres qui ont ce badge
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_badges')
            ->withPivot('earned_at', 'metadata')
            ->withTimestamps();
    }

    /**
     * Badges actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Nom du badge dans la langue courante.
     *
     * La base stocke le libellé français saisi à la création ; la traduction
     * est indexée sur « condition », qui est une clé technique stable
     * (first_presence, streak_7...). Si la clé n'existe pas dans la langue
     * demandée, on retombe sur la valeur enregistrée en base.
     */
    public function translatedName(): string
    {
        $key = "badges.{$this->condition}.name";

        return __($key) === $key ? $this->name : __($key);
    }

    /**
     * Description du badge dans la langue courante, même principe.
     */
    public function translatedDescription(): string
    {
        $key = "badges.{$this->condition}.description";

        return __($key) === $key ? $this->description : __($key);
    }

    /**
     * Retourne la classe CSS de couleur
     */
    public function getColorClassAttribute(): string
    {
        return match($this->color) {
            'gold' => 'bg-yellow-400 text-yellow-900',
            'silver' => 'bg-gray-400 text-gray-900',
            'bronze' => 'bg-orange-400 text-orange-900',
            'green' => 'bg-green-500 text-white',
            'red' => 'bg-red-500 text-white',
            'purple' => 'bg-purple-500 text-white',
            default => 'bg-blue-500 text-white',
        };
    }
}
