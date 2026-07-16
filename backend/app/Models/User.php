<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'telephone',
        'group',
        'role',
        'etablissement_id',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'         => 'datetime',
            'two_factor_confirmed_at'   => 'datetime',
            'must_change_password'      => 'boolean',
        ];
    }

    /**
     * Mutateur : hash automatiquement le mot de passe.
     * Permet de retirer 'password' de $fillable tout en gardant
     * une API simple via forceFill(['password' => $plaintext]).
     */
    public function setPasswordAttribute(string $value): void
    {
        // Ne pas re-hasher si déjà hashé (ex: 60 chars bcrypt)
        if (Hash::needsRehash($value)) {
            $value = Hash::make($value);
        }
        $this->attributes['password'] = $value;
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isFaculteAdmin(): bool
    {
        return $this->role === 'faculte_admin';
    }
}
