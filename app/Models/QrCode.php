<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class QrCode extends Model
{
    protected $fillable = [
        'code',
        'event_name',
        'event_date',
        'expires_at',
        'group',
        'latitude',
        'longitude',
        'radius',
        'location_name'
    ];

    protected $casts = [
        'event_date' => 'date',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'radius' => 'integer'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            $model->code = self::generateTimeBasedCode($model->group);
        });
    }
    
    public static function generateTimeBasedCode($group = null)
    {
        // Générer un code basé sur la minute actuelle et le groupe
        $currentMinute = now()->format('Y-m-d-H-i');
        $groupSuffix = $group ? '-' . $group : '';
        return hash('sha256', $currentMinute . $groupSuffix . config('app.key'));
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isValid()
    {
        return $this->is_active && 
               (!$this->expires_at || $this->expires_at->isFuture()) &&
               $this->event_date->isToday() &&
               $this->isCurrentMinuteValid();
    }
    
    public function isCurrentMinuteValid()
    {
        // Vérifier si le code correspond à la minute actuelle ou précédente (tolérance)
        $currentCode = self::generateTimeBasedCode($this->group);
        $previousCode = hash('sha256', now()->subMinute()->format('Y-m-d-H-i') . '-' . $this->group . config('app.key'));
        
        return $this->code === $currentCode || $this->code === $previousCode;
    }
    
    public function canBeUsedByMember($member)
    {
        // Vérifier si le membre appartient au même groupe que le QR code
        return $this->group === $member->group;
    }
}