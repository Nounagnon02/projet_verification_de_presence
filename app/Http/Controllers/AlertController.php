<?php

namespace App\Http\Controllers;

use App\Models\AlertSetting;
use App\Services\AlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AlertController extends Controller
{
    protected AlertService $alertService;

    public function __construct(AlertService $alertService)
    {
        $this->alertService = $alertService;
    }

    /**
     * Affiche la page de configuration des alertes
     */
    public function index()
    {
        $userGroup = Auth::user()->group;
        $settings = AlertSetting::getOrCreateForGroup(Auth::id(), $userGroup);
        
        // Statistiques d'alertes
        $stats = $this->alertService->getAlertStats($userGroup);
        
        return view('alerts.index', compact('settings', 'stats'));
    }

    /**
     * Met à jour les paramètres d'alertes
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'is_active' => 'boolean',
            'absence_alerts_enabled' => 'boolean',
            'alert_after_minutes' => 'integer|min:5|max:120',
            'event_start_time' => 'required|date_format:H:i',
            'alert_message_template' => 'nullable|string|max:500',
            'reminders_enabled' => 'boolean',
            'reminder_hours_before' => 'integer|min:1|max:72',
            'sms_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'admin_phone' => 'nullable|string|max:20',
            'admin_email' => 'nullable|email|max:255'
        ]);

        $userGroup = Auth::user()->group;
        
        $settings = AlertSetting::updateOrCreate(
            ['user_id' => Auth::id(), 'group' => $userGroup],
            array_merge($validated, [
                'is_active' => $request->boolean('is_active'),
                'absence_alerts_enabled' => $request->boolean('absence_alerts_enabled'),
                'reminders_enabled' => $request->boolean('reminders_enabled'),
                'sms_enabled' => $request->boolean('sms_enabled'),
                'email_enabled' => $request->boolean('email_enabled')
            ])
        );

        return redirect()->route('alerts.index')
            ->with('success', 'Paramètres d\'alertes mis à jour avec succès !');
    }

    /**
     * Déclenche une vérification manuelle des absences
     */
    public function checkNow()
    {
        $userGroup = Auth::user()->group;
        $result = $this->alertService->checkAndSendAbsenceAlerts($userGroup);

        return response()->json($result);
    }

    /**
     * Affiche les membres absents du jour
     */
    public function getAbsentMembers()
    {
        $userGroup = Auth::user()->group;
        $absentMembers = $this->alertService->getAbsentMembers($userGroup, today()->format('Y-m-d'));

        return response()->json([
            'date' => today()->format('d/m/Y'),
            'absent_count' => $absentMembers->count(),
            'members' => $absentMembers->map(fn($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'phone' => $m->phone
            ])
        ]);
    }
}
