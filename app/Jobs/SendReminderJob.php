<?php

namespace App\Jobs;

use App\Models\AlertSetting;
use App\Models\Member;
use App\Models\QrCode;
use App\Services\AlertService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(AlertService $alertService): void
    {
        Log::info("Début du job de rappel de présence");

        // Récupérer tous les paramètres actifs avec rappels activés
        $settings = AlertSetting::where('is_active', true)
            ->where('reminders_enabled', true)
            ->get();

        foreach ($settings as $setting) {
            $this->processGroupReminders($setting, $alertService);
        }

        Log::info("Fin du job de rappel de présence");
    }

    /**
     * Traite les rappels pour un groupe spécifique
     */
    private function processGroupReminders(AlertSetting $setting, AlertService $alertService): void
    {
        // Calculer la date cible pour le rappel
        // Si on veut rappeler 24h avant, on cherche les événements de demain
        $targetDate = Carbon::now()->addHours($setting->reminder_hours_before)->format('Y-m-d');

        // Chercher un événement pour ce groupe à la date cible
        $event = QrCode::where('group', $setting->group)
            ->where('event_date', $targetDate)
            ->first();

        if (!$event) {
            return;
        }

        // Récupérer les membres du groupe
        $members = Member::where('group', $setting->group)->get();

        foreach ($members as $member) {
            // Vérifier si le membre a un téléphone
            if (!empty($member->phone)) {
                $alertService->sendReminder(
                    $member, 
                    $event->event_name ?? 'Séance prévue', 
                    Carbon::parse($targetDate)->format('d/m/Y')
                );
            }
        }
    }
}
