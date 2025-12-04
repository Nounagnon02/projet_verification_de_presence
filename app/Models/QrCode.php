<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class QrCode extends Model
{
    protected $fillable = [
        'code',
        'event_date',
        'event_name',
        'is_active',
        'expires_at',
        'created_by'
    ];

    protected $casts = [
        'event_date' => 'date',
        'expires_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            $model->code = self::generateTimeBasedCode();
        });
    }
    
    public static function generateTimeBasedCode()
    {
        // Générer un code basé sur la minute actuelle
        $currentMinute = now()->format('Y-m-d-H-i');
        return hash('sha256', $currentMinute . config('app.key'));
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
        $currentCode = self::generateTimeBasedCode();
        $previousCode = hash('sha256', now()->subMinute()->format('Y-m-d-H-i') . config('app.key'));
        
        return $this->code === $currentCode || $this->code === $previousCode;
    }
}