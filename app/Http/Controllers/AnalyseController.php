<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\Member;
use App\Models\Presence;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Page « Analyses » : tout ce qui regarde une période.
 *
 * Fusionne les anciennes pages « Analyses avancées » et « Assiduité (heatmap) »,
 * qui répondaient à la même question avec deux sélecteurs de période distincts
 * et des chiffres qui se recouvraient (« Présences (période) » et « Présences
 * totales » comptaient la même chose, « Total membres » était affiché deux fois).
 *
 * La feuille de présence d'un jour donné reste ailleurs : ce n'est pas une
 * analyse, c'est un registre.
 */
class AnalyseController extends Controller
{
    /**
     * Durées proposées, en jours.
     */
    private const PERIODES = [7, 30, 90, 365];

    public function index(Request $request)
    {
        [$dateDebut, $dateFin, $periode, $personnalisee] = $this->resolvePeriode($request);

        // Période précédente de même durée, pour la comparaison
        $dateFinPrec = Carbon::parse($dateDebut)->subDay()->toDateString();
        $dateDebutPrec = Carbon::parse($dateDebut)->subDays($periode)->toDateString();

        $totalMembres = Member::ledBy(Auth::user())->count();
        $presencesActuelles = $this->countPresences($dateDebut, $dateFin);
        $presencesPrecedentes = $this->countPresences($dateDebutPrec, $dateFinPrec);

        $tendance = $presencesPrecedentes > 0
            ? round((($presencesActuelles - $presencesPrecedentes) / $presencesPrecedentes) * 100, 1)
            : 0;

        return view('analyses', [
            'periodes' => self::PERIODES,
            'periode' => $periode,
            'personnalisee' => $personnalisee,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,

            'totalMembres' => $totalMembres,
            'presencesActuelles' => $presencesActuelles,
            'presencesPrecedentes' => $presencesPrecedentes,
            'tendance' => $tendance,
            'tauxActuel' => $totalMembres > 0 ? round(($presencesActuelles / $totalMembres) * 100, 1) : 0,
            'tauxPrecedent' => $totalMembres > 0 ? round(($presencesPrecedentes / $totalMembres) * 100, 1) : 0,

            'presencesParJour' => $this->presencesParJour($dateDebut, $dateFin),
            'membresStats' => $this->classement($dateDebut, $dateFin, $periode),
            'heatmapData' => $this->heatmap($dateDebut, $dateFin),
            'stats' => $this->statsPeriode($dateDebut, $dateFin, $presencesActuelles),
        ]);
    }

    /**
     * Une plage de dates explicite l'emporte sur le préréglage ; la durée est
     * alors recalculée pour que la comparaison et le classement restent justes.
     *
     * @return array{0:string,1:string,2:int,3:bool}
     */
    private function resolvePeriode(Request $request): array
    {
        $debut = $request->query('start_date');
        $fin = $request->query('end_date');

        if ($debut && $fin && strtotime($debut) && strtotime($fin)) {
            $debut = Carbon::parse($debut)->toDateString();
            $fin = Carbon::parse($fin)->toDateString();

            if ($debut > $fin) {
                [$debut, $fin] = [$fin, $debut];
            }

            $jours = max(1, (int) Carbon::parse($debut)->diffInDays(Carbon::parse($fin)) + 1);

            return [$debut, $fin, $jours, true];
        }

        $periode = (int) $request->query('periode', 30);

        if (! in_array($periode, self::PERIODES, true)) {
            $periode = 30;
        }

        return [
            now()->subDays($periode)->toDateString(),
            now()->toDateString(),
            $periode,
            false,
        ];
    }

    private function countPresences(string $debut, string $fin): int
    {
        return Presence::whereHas('member', fn ($q) => $q->ledBy(Auth::user()))
            ->whereBetween('date', [$debut, $fin])
            ->count();
    }

    /**
     * Points de la courbe d'évolution : membres distincts présents par jour.
     */
    private function presencesParJour(string $debut, string $fin)
    {
        return Presence::selectRaw('DATE(date) as jour, COUNT(DISTINCT member_id) as total')
            ->whereHas('member', fn ($q) => $q->ledBy(Auth::user()))
            ->whereBetween('date', [$debut, $fin])
            ->groupBy('jour')
            ->orderBy('jour')
            ->get();
    }

    /**
     * Classement des membres par taux de présence sur la période.
     */
    private function classement(string $debut, string $fin, int $periode)
    {
        return Member::ledBy(Auth::user())
            ->withCount(['presences as total_presences' => fn ($q) => $q->whereBetween('date', [$debut, $fin])])
            ->get()
            ->map(fn ($member) => [
                'name' => $member->name,
                'total_presences' => $member->total_presences,
                'taux_presence' => $periode > 0 ? round(($member->total_presences / $periode) * 100, 1) : 0,
            ])
            ->sortByDesc('taux_presence');
    }

    /**
     * Une entrée par jour de la période, même sans présence, pour que la
     * grille reste continue.
     */
    private function heatmap(string $debut, string $fin): array
    {
        $parJour = Presence::whereHas('member', fn ($q) => $q->ledBy(Auth::user()))
            ->whereBetween('date', [$debut, $fin])
            ->get()
            ->groupBy(fn ($presence) => Carbon::parse($presence->date)->format('Y-m-d'))
            ->map(fn ($jour) => $jour->count());

        $curseur = Carbon::parse($debut);
        $borne = Carbon::parse($fin);
        $data = [];

        while ($curseur <= $borne) {
            $cle = $curseur->format('Y-m-d');
            $total = $parJour[$cle] ?? 0;

            $data[] = [
                'date' => $cle,
                'dayOfWeek' => $curseur->dayOfWeek,
                'week' => $curseur->weekOfYear,
                'total' => $total,
                'intensity' => $this->intensity($total),
            ];

            $curseur->addDay();
        }

        return $data;
    }

    /**
     * Niveau de couleur de 0 à 4.
     */
    private function intensity(int $count): int
    {
        return match (true) {
            $count === 0 => 0,
            $count <= 2 => 1,
            $count <= 5 => 2,
            $count <= 10 => 3,
            default => 4,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function statsPeriode(string $debut, string $fin, int $totalPresences): array
    {
        $groupIds = Auth::user()->groupsLed()->pluck('groups.id');

        $totalEvents = AttendanceSession::whereIn('group_id', $groupIds)
            ->whereBetween('event_date', [$debut, $fin])
            ->distinct('event_date')
            ->count('event_date');

        $meilleurJour = Presence::whereHas('member', fn ($q) => $q->ledBy(Auth::user()))
            ->whereBetween('date', [$debut, $fin])
            ->selectRaw('date, COUNT(*) as count')
            ->groupBy('date')
            ->orderByDesc('count')
            ->first();

        // SQLite en développement, PostgreSQL en production : l'extraction de
        // l'heure n'a pas la même syntaxe.
        $extraction = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%H', time)"
            : 'EXTRACT(HOUR FROM time)';

        $meilleureHeure = Presence::whereHas('member', fn ($q) => $q->ledBy(Auth::user()))
            ->whereBetween('date', [$debut, $fin])
            ->whereNotNull('time')
            ->selectRaw("$extraction as hour, COUNT(*) as count")
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();

        return [
            'total_events' => $totalEvents,
            'avg_per_event' => $totalEvents > 0 ? round($totalPresences / $totalEvents, 1) : 0,
            'best_day' => $meilleurJour
                ? Carbon::parse($meilleurJour->date)->translatedFormat(__('date.day_month'))
                : null,
            'best_day_count' => $meilleurJour->count ?? 0,
            'best_hour' => $meilleureHeure ? $meilleureHeure->hour.' h' : null,
            'best_hour_count' => $meilleureHeure->count ?? 0,
        ];
    }
}
