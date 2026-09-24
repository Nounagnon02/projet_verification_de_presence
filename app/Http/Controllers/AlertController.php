<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Services\AlertService;
use Illuminate\Support\Facades\Auth;

/**
 * Suivi des absences d'un groupe.
 *
 * Il y avait ici un formulaire de réglages (SMS, email, horaires, modèle de
 * message) qui enregistrait des valeurs que rien ne relisait : le seul
 * consommateur était SendReminderJob, jamais planifié ni dispatché. Formulaire
 * et job retirés. La relance se fait par téléphone depuis la liste des absents.
 */
class AlertController extends Controller
{
    protected AlertService $alertService;

    public function __construct(AlertService $alertService)
    {
        $this->alertService = $alertService;
    }

    /**
     * Affiche le suivi des absences pour un groupe
     */
    public function index(Group $group)
    {
        abort_unless($group->leaders->contains(Auth::id()), 403);

        $stats = $this->alertService->getAlertStats($group->id);

        return view('alerts.index', compact('stats', 'group'));
    }

    /**
     * Affiche les membres absents du jour pour un groupe
     */
    public function getAbsentMembers(Group $group)
    {
        abort_unless($group->leaders->contains(Auth::id()), 403);

        $absentMembers = $this->alertService->getAbsentMembers($group->id, today()->format('Y-m-d'));

        return response()->json([
            'date' => today()->translatedFormat(__('date.short')),
            'absent_count' => $absentMembers->count(),
            'members' => $absentMembers->map(fn($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'phone' => $m->phone
            ])
        ]);
    }
}
