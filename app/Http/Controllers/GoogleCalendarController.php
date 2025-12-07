<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\GoogleCalendar\Event;
use Carbon\Carbon;

class GoogleCalendarController extends Controller
{
    /**
     * Affiche la liste des événements et le formulaire de création
     */
    public function index()
    {
        try {
            // Récupérer les événements des 30 prochains jours
            $events = Event::get(
                Carbon::now(),
                Carbon::now()->addDays(30)
            );

            // Trier par date de début
            $events = collect($events)->sortBy(function ($event) {
                return $event->startDateTime ?? $event->startDate;
            });

            return view('calendar.index', [
                'events' => $events,
                'error' => null
            ]);
        } catch (\Exception $e) {
            return view('calendar.index', [
                'events' => collect(),
                'error' => 'Erreur de connexion à Google Calendar: ' . $e->getMessage()
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
        ]);

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

            return redirect()
                ->route('calendar.index')
                ->with('success', 'Événement "' . $request->name . '" créé avec succès !');

        } catch (\Exception $e) {
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
        try {
            $event = Event::find($eventId);
            
            if ($event) {
                $eventName = $event->name;
                $event->delete();
                
                return redirect()
                    ->route('calendar.index')
                    ->with('success', 'Événement "' . $eventName . '" supprimé avec succès !');
            }

            return redirect()
                ->route('calendar.index')
                ->withErrors(['error' => 'Événement non trouvé']);

        } catch (\Exception $e) {
            return redirect()
                ->route('calendar.index')
                ->withErrors(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
        }
    }
}
