<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Auth\Events\Registered;
use App\Models\Presence;

class PresenceController extends Controller
{
    public function dashboard()
    {
        $userGroup = Auth::user()->group;
        $members = member::where('group', $userGroup)->get();

        return view('dashboard', compact('members'));
    }

    public function dashboardV()
    {
        $userGroup = Auth::user()->group;

        return view('dashboardV', compact('userGroup'));
    }
    public function ajout(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20|unique:members,phone',
            ]);

            // Récupérer le groupe de l'utilisateur connecté
            $userGroup = Auth::user()->group;

            $member = Member::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'group' => $userGroup,
                'users_id' => Auth::id(),
            ]);

            event(new Registered($member));

            return redirect()->route('dashboard')->with('success', 'Membre ajouté avec succès!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erreur lors de l\'ajout du membre: ' . $e->getMessage())->withInput();
        }
    }

    public function verif(Request $request): RedirectResponse
    {
        $request->validate([
            'nometprenoms' => 'required|string',
        ]);

        $nometprenoms = $request->nometprenoms;
        $userGroup = Auth::user()->group;

        // Rechercher le membre dans le groupe de l'utilisateur
        $member = Member::where('name', $nometprenoms)
                    ->where('group', $userGroup)
                    ->first();

        if ($member) {
            // Enregistrer la présence
            Presence::firstOrCreate([
                'member_id' => $member->id,
                'date' => now()->toDateString(),
            ], [
                'time' => now()->toTimeString(),
            ]);

            return redirect()->route('dashboardV')->with('verification_result', 'Vérification réussie! ' . $nometprenoms . ' est présent(e).');
        }

        return redirect()->route('dashboardV')->with('verification_error', 'Désolé, ' . $nometprenoms . ' n\'est pas enregistré(e) dans votre groupe.');
    }

    public function statistiques(Request $request): View
    {
        $userGroup = Auth::user()->group;
        $date = $request->input('date', now()->toDateString());
        $search = $request->input('search');

        // Récupérer les présences pour le groupe de l'utilisateur
        $query = Presence::with(['member' => function($query) use ($userGroup) {
                $query->where('group', $userGroup);
            }])
            ->whereHas('member', function($query) use ($userGroup) {
                $query->where('group', $userGroup);
            })
            ->whereDate('date', $date)
            ->orderBy('time', 'desc');

        if ($search) {
            $query->whereHas('member', function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        $presences = $query->get();

        // Total des membres pour le groupe
        $totalMembres = Member::where('group', $userGroup)->count();
        $totalPresent = $presences->count();
        $tauxPresence = $totalMembres > 0 ? round(($totalPresent / $totalMembres) * 100, 2) : 0;

        return view('statistiques', compact('presences', 'totalPresent', 'totalMembres', 'tauxPresence', 'date', 'search'));
    }
}
