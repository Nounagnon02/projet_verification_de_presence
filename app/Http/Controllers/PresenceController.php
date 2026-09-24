<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Group;
use App\Models\MemberQrCode;
use App\Models\Presence;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\RegularityScoreService;

class PresenceController extends Controller
{
    public function dashboard()
    {
        $members = Member::ledBy(Auth::user())->get();
        $groups = Auth::user()->groupsLed()->withCount('members')->get();
        $activeSessions = AttendanceSession::whereIn('group_id', $groups->pluck('id'))
            ->where('is_active', true)
            ->get()
            ->keyBy('group_id');

        return view('dashboard', compact('members', 'groups', 'activeSessions'));
    }

    public function dashboardV()
    {
        $groups = Auth::user()->groupsLed()->with(['members' => function ($q) {
            $q->orderBy('name');
        }])->get();

        $today = now()->toDateString();
        $presencesToday = Presence::whereDate('date', $today)
            ->whereHas('member', fn ($q) => $q->ledBy(Auth::user()))
            ->pluck('member_id')
            ->toArray();

        return view('dashboardV', compact('groups', 'presencesToday'));
    }

    public function ajoutMultiple(Request $request): RedirectResponse
    {
        $request->validate([
            'members' => 'required|array|min:1',
            'members.*.name' => 'required|string|max:255',
            'members.*.phone' => 'required|string|max:20|unique:members,phone|regex:/^[\+0-9]+$/',
            'members.*.rgpd_consent' => 'required|accepted',
            'group_ids' => 'required|array|min:1',
            'group_ids.*' => 'exists:groups,id',
        ], [
            'members.*.phone.regex' => __('Le numéro de téléphone ne peut contenir que des chiffres et le symbole +'),
        ]);

        // Restreindre aux groupes réellement dirigés par le responsable connecté
        $groupIds = Auth::user()->groupsLed()->whereIn('groups.id', $request->group_ids)->pluck('groups.id');

        abort_if($groupIds->isEmpty(), 403);

        $count = 0;

        foreach ($request->members as $memberData) {
            $member = Member::create([
                'name' => $memberData['name'],
                'phone' => $memberData['phone'],
                'users_id' => Auth::id(),
                'rgpd_consent' => true,
                'rgpd_consent_at' => now(),
                'consent_method' => 'oral'
            ]);

            $member->groups()->attach($groupIds);

            // Émission automatique du QR personnel du membre (règle de gestion §4)
            MemberQrCode::create([
                'member_id' => $member->id,
                'token' => MemberQrCode::generateToken(),
            ]);

            $count++;
        }

        return redirect()->route('membres')->with('success', trans_choice(':count membre ajouté.|:count membres ajoutés.', $count));
    }

    /**
     * Pointage manuel (sans scan QR) pour un groupe, sur sa session active.
     */
    public function verif(Request $request, Group $group): RedirectResponse
    {
        abort_unless($group->leaders->contains(Auth::id()), 403);

        $request->validate([
            'presences' => 'array',
            'presences.*' => 'exists:members,id',
        ]);

        $session = AttendanceSession::where('group_id', $group->id)->where('is_active', true)->first();

        if (!$session) {
            return redirect()->back()->with('error', __('Aucune session active pour ce groupe.'));
        }

        $memberIds = $request->input('presences', []);
        $count = 0;

        foreach ($memberIds as $memberId) {
            $member = Member::whereHas('groups', fn ($q) => $q->where('groups.id', $group->id))
                ->where('id', $memberId)
                ->first();

            if (!$member) {
                continue;
            }

            $exists = Presence::where('member_id', $member->id)
                ->where('attendance_session_id', $session->id)
                ->exists();

            if ($exists) {
                continue;
            }

            Presence::create([
                'member_id' => $member->id,
                'attendance_session_id' => $session->id,
                'scanned_by' => Auth::id(),
                'date' => $session->event_date,
                'time' => now(),
                'verification_method' => 'manual',
            ]);
            $count++;
        }

        return redirect()->route('dashboardV')->with('verification_result', $count . ' présence(s) enregistrée(s) avec succès!');
    }

    public function statistiques(Request $request)
    {
        $date = $request->input('date', now()->toDateString());
        $search = $request->input('search');
        $export = $request->input('export');

        $query = Presence::with('member')
            ->whereHas('member', fn ($q) => $q->ledBy(Auth::user()))
            ->whereDate('date', $date)
            ->orderBy('time', 'desc');

        if ($search) {
            $query->whereHas('member', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        $presences = $query->get();

        $totalMembres = Member::ledBy(Auth::user())->count();
        $totalPresent = $presences->count();
        $tauxPresence = $totalMembres > 0 ? round(($totalPresent / $totalMembres) * 100, 2) : 0;

        // Si export PDF demandé
        if ($export === 'pdf') {
            $pdf = Pdf::loadView('pdf.statistiques', compact('presences', 'totalPresent', 'totalMembres', 'tauxPresence', 'date', 'search'));
            return $pdf->download('statistiques-presence-' . $date . '.pdf');
        }

        // Audit trail récent
        $auditLogs = AuditLog::with('user')
            ->where('model_type', 'App\\Models\\Presence')
            ->latest()
            ->take(5)
            ->get();

        return view('statistiques', compact('presences', 'totalPresent', 'totalMembres', 'tauxPresence', 'date', 'search', 'auditLogs'));
    }

    public function statistiquesAvancees(Request $request)
    {
        $periode = (int) $request->input('periode', '30'); // 7, 30, 90, 365 jours

        $dateDebut = now()->subDays($periode)->toDateString();
        $dateFin = now()->toDateString();
        $dateDebutPrec = now()->subDays($periode * 2)->toDateString();
        $dateFinPrec = now()->subDays($periode)->toDateString();

        $totalMembres = Member::ledBy(Auth::user())->count();

        $presencesParJour = Presence::selectRaw('DATE(date) as jour, COUNT(DISTINCT member_id) as total')
            ->whereHas('member', fn ($q) => $q->ledBy(Auth::user()))
            ->whereBetween('date', [$dateDebut, $dateFin])
            ->groupBy('jour')
            ->orderBy('jour')
            ->get();

        $membresStats = Member::ledBy(Auth::user())
            ->withCount(['presences as total_presences' => function ($query) use ($dateDebut, $dateFin) {
                $query->whereBetween('date', [$dateDebut, $dateFin]);
            }])
            ->get()
            ->map(function ($member) use ($periode) {
                $tauxPresence = $periode > 0 ? round(($member->total_presences / $periode) * 100, 1) : 0;
                return [
                    'name' => $member->name,
                    'total_presences' => $member->total_presences,
                    'taux_presence' => $tauxPresence
                ];
            })
            ->sortByDesc('taux_presence');

        $presencesActuelles = Presence::whereHas('member', fn ($q) => $q->ledBy(Auth::user()))
            ->whereBetween('date', [$dateDebut, $dateFin])
            ->count();

        $presencesPrecedentes = Presence::whereHas('member', fn ($q) => $q->ledBy(Auth::user()))
            ->whereBetween('date', [$dateDebutPrec, $dateFinPrec])
            ->count();

        $tendance = $presencesPrecedentes > 0 ?
            round((($presencesActuelles - $presencesPrecedentes) / $presencesPrecedentes) * 100, 1) : 0;

        $tauxActuel = $totalMembres > 0 ? round(($presencesActuelles / $totalMembres) * 100, 1) : 0;
        $tauxPrecedent = $totalMembres > 0 ? round(($presencesPrecedentes / $totalMembres) * 100, 1) : 0;

        return view('statistiques-avancees', compact(
            'totalMembres', 'presencesParJour', 'membresStats', 'tendance',
            'periode', 'presencesActuelles', 'presencesPrecedentes',
            'tauxActuel', 'tauxPrecedent'
        ));
    }

    // Gestion des membres
    public function listeMembres()
    {
        $groups = Auth::user()->groupsLed;

        $membres = Member::ledBy(Auth::user())
            ->orderBy('name')
            ->paginate(10);

        $scoreService = new RegularityScoreService();

        $scores = [];
        foreach ($membres as $membre) {
            $scores[$membre->id] = $scoreService->calculateScore($membre);
        }

        $ranking = Member::ledBy(Auth::user())->get()
            ->map(function ($membre) use ($scoreService) {
                $data = $scoreService->calculateScore($membre);
                return [
                    'member' => $membre,
                    'score' => $data['score'],
                    'level' => $data['level'],
                    'presences' => $data['total_presences'],
                    'events' => $data['total_events'],
                ];
            })
            ->sortByDesc('score')
            ->take(5)
            ->values()
            ->all();

        return view('membres.index', compact('membres', 'scores', 'ranking', 'groups'));
    }

    public function editMembre($id)
    {
        $membre = Member::ledBy(Auth::user())->where('id', $id)->firstOrFail();
        $groups = Auth::user()->groupsLed;
        $membreGroupIds = $membre->groups->pluck('id');

        return view('membres.edit', compact('membre', 'groups', 'membreGroupIds'));
    }

    public function updateMembre(Request $request, $id)
    {
        $membre = Member::ledBy(Auth::user())->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:members,phone,' . $id,
            'group_ids' => 'required|array|min:1',
            'group_ids.*' => 'exists:groups,id',
        ]);

        $membre->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
        ]);

        // Ne (dé)synchroniser que les groupes dirigés par le responsable connecté :
        // ses appartenances à d'autres groupes qu'il ne dirige pas restent inchangées.
        $ledGroupIds = Auth::user()->groupsLed()->pluck('groups.id');
        $selectedLedGroupIds = collect($validated['group_ids'])->intersect($ledGroupIds);
        $otherGroupIds = $membre->groups()->pluck('groups.id')->diff($ledGroupIds);
        $membre->groups()->sync($otherGroupIds->merge($selectedLedGroupIds));

        return redirect()->route('membres')->with('success', __('Membre modifié.'));
    }

    public function deleteMembre($id)
    {
        $membre = Member::ledBy(Auth::user())->where('id', $id)->firstOrFail();

        // Retire le membre uniquement des groupes dirigés par le responsable connecté.
        $ledGroupIds = Auth::user()->groupsLed()->pluck('groups.id');
        $membre->groups()->detach($ledGroupIds);

        // S'il ne reste plus rattaché à aucun groupe, on supprime le membre et son historique.
        if ($membre->groups()->count() === 0) {
            Presence::where('member_id', $id)->delete();
            $membre->qrCodes()->delete();
            $membre->delete();
        }

        return redirect()->route('membres')->with('success', __('Membre supprimé.'));
    }

    public function printCard(Member $member)
    {
        abort_unless($member->groups->pluck('id')->intersect(Auth::user()->groupsLed()->pluck('groups.id'))->isNotEmpty(), 403);

        $qrCode = $member->qrCode;
        abort_if(!$qrCode, 404, __("Ce membre n'a pas de QR personnel actif."));

        // PNG (pas SVG) : DomPDF ne rend pas correctement le SVG généré par les
        // librairies QR courantes, et imagick n'est pas disponible pour du PNG
        // via bacon/simple-qrcode — chillerlan/php-qrcode fonctionne en pur GD.
        $qrOptions = new \chillerlan\QRCode\QROptions([
            'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
            'scale' => 6,
            'outputBase64' => false,
        ]);
        $qrPng = (new \chillerlan\QRCode\QRCode($qrOptions))->render($qrCode->token);
        $qrImageBase64 = 'data:image/png;base64,' . base64_encode($qrPng);

        $pdf = Pdf::loadView('pdf.carte-membre', compact('member', 'qrImageBase64'));

        return $pdf->stream('carte-' . $member->id . '.pdf');
    }

}
