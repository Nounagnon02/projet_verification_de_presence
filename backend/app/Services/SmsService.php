<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use YasserElgammal\LaraSms\Facades\Sms;

class SmsService
{
    public function sendMemberCode($phone, $memberCode, $memberName)
    {
        $message = "Bonjour {$memberName}, votre code membre est: {$memberCode}. Conservez-le précieusement.";
        
        // Log pour développement
        Log::info("Tentative d'envoi SMS à {$phone}: {$message}");
        
        try {
            // Utilisation de Lara SMS
            $result = Sms::send($phone, $message);
            
            Log::info("SMS envoyé avec succès à {$phone}");
            return [
                'success' => true,
                'message' => 'SMS envoyé avec succès'
            ];
            
        } catch (\Exception $e) {
            Log::error("Exception envoi SMS à {$phone}: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du SMS: ' . $e->getMessage()
            ];
        }
    }
    
    public function generateMemberCode()
    {
        // Génère un code à 8 chiffres unique
        do {
            $code = str_pad(random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        } while (\App\Models\Member::where('member_code', $code)->exists());
        
        return $code;
    }

}