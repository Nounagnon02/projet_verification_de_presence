<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function members()
    {
        return $this->hasMany(Member::class);
    }

    public function presences()
    {
        return $this->hasManyThrough(Presence::class, Member::class);
    }

    public function groupsLed(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_leaders');
    }
}
