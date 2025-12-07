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
