<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use Auditable;
    protected $fillable = [
        'name',
        'phone',
        'group',
        'users_id',
        'rgpd_consent',
        'rgpd_consent_at',
        'consent_method'
    ];
    
    protected $casts = [
        'rgpd_consent_at' => 'datetime',
        'rgpd_consent' => 'boolean'
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
