<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\GoogleCalendar\Event;
use Carbon\Carbon;

class GoogleCalendarController extends Controller
{
    /**
     * Affiche la liste des événements et le formulaire de création.
     *
     * Un seul calendrier Google est partagé par toute l'application : l'isolement
     * par groupe se fait via la table locale calendar_events, pas via plusieurs
     * calendriers Google. Les événements Google sans ligne correspondante
     * (créés avant cet isolement) restent visibles pour ne rien faire disparaître.
     */
    public function index()
    {
        try {
            // Récupérer les événements des 30 prochains jours
            $events = Event::get(
                Carbon::now(),
                Carbon::now()->addDays(30)
            );

            $ledGroupIds = Auth::user()->groupsLed()->pluck('groups.id');
            $mappings = CalendarEvent::whereIn('google_event_id', collect($events)->pluck('id'))
                ->get()
                ->keyBy('google_event_id');

            $events = collect($events)->filter(function ($event) use ($mappings, $ledGroupIds) {
                $mapping = $mappings->get($event->id);
                // Événement non rattaché à un groupe (créé avant l'isolement) : toujours visible.
                return !$mapping || $ledGroupIds->contains($mapping->group_id);
            })->sortBy(function ($event) {
                return $event->startDateTime ?? $event->startDate;
            });

            return view('calendar.index', [
                'events' => $events,
                'error' => null,
                'groups' => Auth::user()->groupsLed,
            ]);
        } catch (\Throwable $e) {
            return view('calendar.index', [
                'events' => collect(),
                'error' => 'Erreur de connexion à Google Calendar: ' . $e->getMessage(),
                'groups' => Auth::user()->groupsLed,
            ]);
        }
    }

    /**
     * Créer un nouvel événement
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'start_time' => 'required',
            'end_date' => 'required|date',
            'end_time' => 'required',
            'group_id' => 'required|exists:groups,id',
        ]);

        $ledGroupIds = Auth::user()->groupsLed()->pluck('groups.id');
        abort_unless($ledGroupIds->contains((int) $request->group_id), 403);

        try {
            $startDateTime = Carbon::parse($request->start_date . ' ' . $request->start_time);
            $endDateTime = Carbon::parse($request->end_date . ' ' . $request->end_time);

            // Validation: la date de fin doit être après la date de début
            if ($endDateTime->lte($startDateTime)) {
                return back()
                    ->withErrors(['end_date' => 'La date/heure de fin doit être après la date/heure de début'])
                    ->withInput();
            }

            $event = new Event;
            $event->name = $request->name;
            $event->description = $request->description ?? '';
            $event->startDateTime = $startDateTime;
            $event->endDateTime = $endDateTime;

            // Gestion de la récurrence (RRULE)
            if ($request->recurrence_type && $request->recurrence_type !== 'none') {
                $rrule = [];
                
                if ($request->recurrence_type === 'custom') {
                    // Mode personnalisé
                    $freq = $request->freq ?? 'WEEKLY';
                    $rrule[] = "FREQ={$freq}";
                    
                    if ($request->interval > 1) {
                        $rrule[] = "INTERVAL={$request->interval}";
                    }
                    
                    // Fin de la récurrence
                    if ($request->end_type === 'date' && $request->until_date) {
                        // Format YYYYMMDDTHHMMSSZ pour iCal
                        $until = Carbon::parse($request->until_date)->endOfDay()->format('Ymd\THis\Z');
                        $rrule[] = "UNTIL={$until}";
                    } elseif ($request->end_type === 'count' && $request->count) {
                        $rrule[] = "COUNT={$request->count}";
                    }
                } else {
                    // Modes prédéfinis (DAILY, WEEKLY, MONTHLY, YEARLY)
                    $rrule[] = "FREQ={$request->recurrence_type}";
                }
                
                // Assigner la règle de récurrence
                $event->recurrence = ['RRULE:' . implode(';', $rrule)];
            }

            $event->save();

            CalendarEvent::create([
                'google_event_id' => $event->id,
                'group_id' => $request->group_id,
                'created_by' => Auth::id(),
            ]);

            return redirect()
                ->route('calendar.index')
                ->with('success', 'Événement "' . $request->name . '" créé avec succès !');

        } catch (\Throwable $e) {
            return back()
                ->withErrors(['error' => 'Erreur lors de la création: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Supprimer un événement
     */
    public function destroy($eventId)
    {
        $mapping = CalendarEvent::where('google_event_id', $eventId)->first();

        if ($mapping) {
            $ledGroupIds = Auth::user()->groupsLed()->pluck('groups.id');
            abort_unless($ledGroupIds->contains($mapping->group_id), 403);
        }

        try {
            $event = Event::find($eventId);

            if ($event) {
                $eventName = $event->name;
                $event->delete();
                $mapping?->delete();

                return redirect()
                    ->route('calendar.index')
                    ->with('success', 'Événement "' . $eventName . '" supprimé avec succès !');
            }

            return redirect()
                ->route('calendar.index')
                ->withErrors(['error' => 'Événement non trouvé']);

        } catch (\Throwable $e) {
            return redirect()
                ->route('calendar.index')
                ->withErrors(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
        }
    }
}
