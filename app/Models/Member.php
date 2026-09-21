<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Member extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'phone',
        'users_id',
        'rgpd_consent',
        'rgpd_consent_at',
        'consent_method',
    ];

    protected $casts = [
        'rgpd_consent_at' => 'datetime',
        'rgpd_consent' => 'boolean',
    ];

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'member_badges')
            ->withPivot('earned_at', 'metadata')
            ->withTimestamps();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_member');
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(MemberQrCode::class);
    }

    public function qrCode(): HasOne
    {
        return $this->hasOne(MemberQrCode::class)->where('is_active', true);
    }

    /**
     * Membres des groupes dirigés par cet utilisateur (co-responsable).
     */
    public function scopeLedBy($query, User $user)
    {
        return $query->whereHas('groups.leaders', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        });
    }

    /**
     * Retourne le nombre total de points de badges
     */
    public function getTotalBadgePointsAttribute(): int
    {
        return $this->badges->sum('points');
    }
}
