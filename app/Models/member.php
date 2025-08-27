<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'group',
        'users_id'
    ];

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scope pour filtrer par groupe utilisateur
    public function scopeForUserGroup($query, $userId)
    {
        $userGroup = User::find($userId)->group;
        return $query->where('group', $userGroup);
    }
}
