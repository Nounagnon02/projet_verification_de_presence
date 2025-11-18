<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\Response;
use Illuminate\Auth\Events\Registered;
use App\Models\Presence;
use Barryvdh\DomPDF\Facade\Pdf;

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
        $members = Member::where('group', $userGroup)->orderBy('name')->get();
        
        // Récupérer les présences du jour
        $today = now()->toDateString();
        $presencesToday = Presence::whereDate('date', $today)
            ->whereHas('member', function($query) use ($userGroup) {
                $query->where('group', $userGroup);
            })
            ->pluck('member_id')
            ->toArray();

        return view('dashboardV', compact('userGroup', 'members', 'presencesToday'));
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

    public function ajoutMultiple(Request $request): RedirectResponse
    {
        $request->validate([
            'members' => 'required|array|min:1',
            'members.*.name' => 'required|string|max:255',
            'members.*.phone' => 'required|string|max:20|unique:members,phone',
        ]);

        $userGroup = Auth::user()->group;
        $count = 0;

        foreach ($request->members as $memberData) {
            Member::create([
                'name' => $memberData['name'],
                'phone' => $memberData['phone'],
                'group' => $userGroup,
                'users_id' => Auth::id(),
            ]);
            $count++;
        }

        return redirect()->route('dashboard')->with('success', $count . ' membre(s) ajouté(s) avec succès!');
    }

    public function verif(Request $request): RedirectResponse
    {
        $request->validate([
            'presences' => 'array',
            'presences.*' => 'exists:members,id'
        ]);

        $userGroup = Auth::user()->group;
        $memberIds = $request->input('presences', []);
        $today = now()->toDateString();
        $currentTime = now()->toTimeString();
        
        $count = 0;
        foreach ($memberIds as $memberId) {
            // Vérifier que le membre appartient au groupe
            $member = Member::where('id', $memberId)->where('group', $userGroup)->first();
            if ($member) {
                Presence::firstOrCreate([
                    'member_id' => $memberId,
                    'date' => $today,
                ], [
                    'time' => $currentTime,
                ]);
                $count++;
            }
        }

        return redirect()->route('dashboardV')->with('verification_result', $count . ' présence(s) enregistrée(s) avec succès!');
    }

    public function statistiques(Request $request)
    {
        $userGroup = Auth::user()->group;
        $date = $request->input('date', now()->toDateString());
        $search = $request->input('search');
        $export = $request->input('export');

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

        // Si export PDF demandé
        if ($export === 'pdf') {
            $pdf = Pdf::loadView('pdf.statistiques', compact('presences', 'totalPresent', 'totalMembres', 'tauxPresence', 'date', 'search', 'userGroup'));
            return $pdf->download('statistiques-presence-' . $date . '.pdf');
        }

        return view('statistiques', compact('presences', 'totalPresent', 'totalMembres', 'tauxPresence', 'date', 'search'));
    }

    // Gestion des membres
    public function listeMembres()
    {
        $userGroup = Auth::user()->group;
        $membres = Member::where('group', $userGroup)
                        ->orderBy('name')
                        ->paginate(10);

        return view('membres.index', compact('membres'));
    }

    public function editMembre($id)
    {
        $userGroup = Auth::user()->group;
        $membre = Member::where('id', $id)
                       ->where('group', $userGroup)
                       ->firstOrFail();

        return view('membres.edit', compact('membre'));
    }

    public function updateMembre(Request $request, $id)
    {
        $userGroup = Auth::user()->group;
        $membre = Member::where('id', $id)
                       ->where('group', $userGroup)
                       ->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:members,phone,' . $id,
        ]);

        $membre->update($validated);

        return redirect()->route('membres')->with('success', 'Membre modifié avec succès!');
    }

    public function deleteMembre($id)
    {
        $userGroup = Auth::user()->group;
        $membre = Member::where('id', $id)
                       ->where('group', $userGroup)
                       ->firstOrFail();

        // Supprimer aussi les présences associées
        Presence::where('member_id', $id)->delete();
        $membre->delete();

        return redirect()->route('membres')->with('success', 'Membre supprimé avec succès!');
    }
}
