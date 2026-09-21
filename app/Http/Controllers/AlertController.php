<?php

namespace App\Http\Controllers;

use App\Models\AlertSetting;
use App\Models\Group;
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
     * Affiche la page de configuration des alertes pour un groupe
     */
    public function index(Group $group)
    {
        abort_unless($group->leaders->contains(Auth::id()), 403);

        $settings = AlertSetting::getOrCreateForGroup(Auth::id(), $group->id);

        // Statistiques d'alertes
        $stats = $this->alertService->getAlertStats($group->id);

        return view('alerts.index', compact('settings', 'stats', 'group'));
    }

    /**
     * Met à jour les paramètres d'alertes d'un groupe
     */
    public function update(Request $request, Group $group)
    {
        abort_unless($group->leaders->contains(Auth::id()), 403);

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

        AlertSetting::updateOrCreate(
            ['user_id' => Auth::id(), 'group_id' => $group->id],
            array_merge($validated, [
                'is_active' => $request->boolean('is_active'),
                'absence_alerts_enabled' => $request->boolean('absence_alerts_enabled'),
                'reminders_enabled' => $request->boolean('reminders_enabled'),
                'sms_enabled' => $request->boolean('sms_enabled'),
                'email_enabled' => $request->boolean('email_enabled')
            ])
        );

        return redirect()->route('alerts.index', $group)
            ->with('success', 'Paramètres d\'alertes mis à jour avec succès !');
    }

    /**
     * Déclenche une vérification manuelle des absences pour un groupe
     */
    public function checkNow(Group $group)
    {
        abort_unless($group->leaders->contains(Auth::id()), 403);

        $result = $this->alertService->checkAndSendAbsenceAlerts($group->id);

        return response()->json($result);
    }

    /**
     * Affiche les membres absents du jour pour un groupe
     */
    public function getAbsentMembers(Group $group)
    {
        abort_unless($group->leaders->contains(Auth::id()), 403);

        $absentMembers = $this->alertService->getAbsentMembers($group->id, today()->format('Y-m-d'));

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
