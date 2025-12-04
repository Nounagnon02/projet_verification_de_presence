<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceVerification extends Model
{
    protected $fillable = [
        'device_fingerprint',
        'ip_address',
        'last_verification'
    ];

    protected $casts = [
        'last_verification' => 'datetime'
    ];

    public static function canVerify($deviceFingerprint, $ipAddress)
    {
        $recent = self::where('device_fingerprint', $deviceFingerprint)
            ->where('last_verification', '>', now()->subHours(2))
            ->first();

        return !$recent;
    }

    public static function recordVerification($deviceFingerprint, $ipAddress)
    {
        return self::updateOrCreate(
            ['device_fingerprint' => $deviceFingerprint],
            [
                'ip_address' => $ipAddress,
                'last_verification' => now()
            ]
        );
    }
}