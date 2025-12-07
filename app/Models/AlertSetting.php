<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertSetting extends Model
{
    protected $fillable = [
        'user_id',
        'group',
        'is_active',
        'absence_alerts_enabled',
        'alert_after_minutes',
        'event_start_time',
        'alert_message_template',
        'reminders_enabled',
        'reminder_hours_before',
        'sms_enabled',
        'email_enabled',
        'admin_phone',
        'admin_email'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'absence_alerts_enabled' => 'boolean',
        'reminders_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'alert_after_minutes' => 'integer',
        'reminder_hours_before' => 'integer',
        'event_start_time' => 'datetime:H:i'
    ];

    /**
     * L'utilisateur propriétaire des paramètres
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Récupère ou crée les paramètres pour un groupe
     */
    public static function getOrCreateForGroup(int $userId, string $group): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId, 'group' => $group],
            [
                'is_active' => true,
                'absence_alerts_enabled' => true,
                'alert_after_minutes' => 30,
                'event_start_time' => '09:00:00',
                'reminders_enabled' => true,
                'reminder_hours_before' => 24,
                'sms_enabled' => true
            ]
        );
    }

    /**
     * Template de message par défaut
     */
    public function getMessageTemplateAttribute(): string
    {
        return $this->alert_message_template ?? 
            "Bonjour {name}, vous n'êtes pas encore enregistré pour l'événement du {date}. N'oubliez pas de pointer !";
    }
}
