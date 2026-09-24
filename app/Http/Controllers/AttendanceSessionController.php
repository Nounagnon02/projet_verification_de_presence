<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\Badge;
use App\Models\Group;
use App\Models\MemberQrCode;
use App\Models\Presence;
use App\Services\AnomalyDetectionService;
use App\Services\BadgeService;
use App\Services\GeofenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceSessionController extends Controller
{
    protected GeofenceService $geofenceService;
    protected AnomalyDetectionService $anomalyService;
    protected BadgeService $badgeService;

    public function __construct(
        GeofenceService $geofenceService,
        AnomalyDetectionService $anomalyService,
        BadgeService $badgeService
    ) {
        $this->geofenceService = $geofenceService;
        $this->anomalyService = $anomalyService;
        $this->badgeService = $badgeService;
    }

    /**
     * Ouvre une session de présence pour un groupe.
     */
    public function open(Request $request, Group $group)
    {
        abort_unless($group->leaders->contains(Auth::id()), 403);

        $request->validate([
            'event_name' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'radius' => 'nullable|integer|min:1',
            'location_name' => 'nullable|string|max:255',
        ]);

        try {
            $session = AttendanceSession::create([
                'group_id' => $group->id,
                'event_name' => $request->event_name,
                'event_date' => today(),
                'opened_by' => Auth::id(),
                'opened_at' => now(),
                'is_active' => true,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'radius' => $request->radius,
                'location_name' => $request->location_name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->with('error', __("Une session est déjà active pour ce groupe. Fermez-la avant d'en ouvrir une nouvelle."));
        }

        return redirect()->route('sessions.scan', $session)->with('success', __('Session ouverte.'));
    }

    /**
     * Ferme manuellement une session de présence.
     */
    public function close(AttendanceSession $session)
    {
        abort_unless($session->group->leaders->contains(Auth::id()), 403);

        $session->update(['is_active' => false, 'closed_at' => now()]);

        return redirect()->route('dashboard')->with('success', __('Session fermée.'));
    }

    /**
     * Affiche l'écran de scan du responsable pour une session ouverte.
     */
    public function showScan(AttendanceSession $session)
    {
        abort_unless($session->group->leaders->contains(Auth::id()), 403);
        abort_unless($session->is_active, 404);

        return view('sessions.open', compact('session'));
    }

    /**
     * Enregistre une présence à partir du jeton QR scanné par le responsable.
     */
    public function scan(Request $request, AttendanceSession $session)
    {
        abort_unless($session->group->leaders->contains(Auth::id()), 403);

        if (!$session->is_active) {
            return response()->json(['error' => __('Cette session est fermée.')], 400);
        }

        $request->validate([
            'token' => 'required|string',
        ]);

        // Vérifier la géolocalisation du responsable si la session en définit une
        if ($request->latitude && $request->longitude) {
            $geoResult = $this->geofenceService->isLocationValid(
                $session,
                (float) $request->latitude,
                (float) $request->longitude
            );

            if (!$geoResult['valid']) {
                return response()->json([
                    'error' => "Vous êtes trop loin du lieu de la session ({$geoResult['distance']}m). Rayon autorisé: {$geoResult['radius']}m."
                ], 403);
            }
        }

        $qrCode = MemberQrCode::where('token', $request->token)->where('is_active', true)->first();

        if (!$qrCode) {
            return response()->json(['error' => __('QR code invalide ou révoqué.')], 400);
        }

        $member = $qrCode->member;

        if (!$member->groups->contains($session->group_id)) {
            return response()->json(['error' => "Ce membre n'appartient pas à ce groupe."], 403);
        }

        $alreadyScanned = Presence::where('member_id', $member->id)
            ->where('attendance_session_id', $session->id)
            ->exists();

        if ($alreadyScanned) {
            return response()->json(['error' => "{$member->name} a déjà été scanné pour cette session."], 409);
        }

        $presence = Presence::create([
            'member_id' => $member->id,
            'attendance_session_id' => $session->id,
            'scanned_by' => Auth::id(),
            'date' => $session->event_date,
            'time' => now(),
            'verification_method' => 'qr_scan',
        ]);

        $newBadges = $this->badgeService->checkAndAwardBadges($member);

        $this->anomalyService->checkAnomalies($member, [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Présence enregistrée pour :name', ['name' => $member->name]),
            // Le nom du badge est traduit dans la langue de la requête ; la
            // base garde le libellé français d'origine comme repli.
            'new_badges' => collect($newBadges)->map(fn (Badge $badge) => $badge->translatedName()),
        ]);
    }
}
