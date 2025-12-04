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
use App\Services\SmsService;

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
                'users_id' => Auth::id()
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
            'members.*.phone' => 'required|string|max:20|unique:members,phone|regex:/^[\+0-9]+$/',
            'members.*.rgpd_consent' => 'required|accepted',
        ], [
            'members.*.phone.regex' => 'Le numéro de téléphone ne peut contenir que des chiffres et le symbole +',
        ]);

        $userGroup = Auth::user()->group;
        $count = 0;

        foreach ($request->members as $memberData) {
            // Créer le membre
            $member = Member::create([
                'name' => $memberData['name'],
                'phone' => $memberData['phone'],
                'group' => $userGroup,
                'users_id' => Auth::id(),
                'rgpd_consent' => true,
                'rgpd_consent_at' => now(),
                'consent_method' => 'oral'
            ]);
            
            $count++;
        }

        return redirect()->route('dashboard')->with('success', $count . ' membre(s) ajouté(s) avec succès!');
    }

    public function verif(Request $request): RedirectResponse
    {
        $request->validate([
            'presences' => 'array',
            'presences.*' => 'exists:members,id',
            'signature' => 'nullable|string'
        ]);

        $userGroup = Auth::user()->group;
        $memberIds = $request->input('presences', []);
        $signature = $request->input('signature');
        $today = now()->toDateString();
        $currentTime = now()->toTimeString();
        
        $count = 0;
        foreach ($memberIds as $memberId) {
            // Vérifier que le membre appartient au groupe
            $member = Member::where('id', $memberId)->where('group', $userGroup)->first();
            if ($member) {
                Presence::updateOrCreate([
                    'member_id' => $memberId,
                    'date' => $today,
                ], [
                    'time' => $currentTime,
                    'signature' => $signature,
                    'verification_method' => 'manual',
                    'signed_at' => $signature ? now() : null
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

        // Audit trail récent
        $auditLogs = \App\Models\AuditLog::with('user')
            ->where('model_type', 'App\\Models\\Presence')
            ->latest()
            ->take(5)
            ->get();
            
        return view('statistiques', compact('presences', 'totalPresent', 'totalMembres', 'tauxPresence', 'date', 'search', 'auditLogs'));
    }

    public function statistiquesAvancees(Request $request)
    {
        $userGroup = Auth::user()->group;
        $periode = $request->input('periode', '30'); // 7, 30, 90 jours
        $dateDebut = now()->subDays($periode)->toDateString();
        $dateFin = now()->toDateString();

        // Statistiques générales
        $totalMembres = Member::where('group', $userGroup)->count();
        
        // Présences par jour sur la période
        $presencesParJour = Presence::selectRaw('DATE(date) as jour, COUNT(DISTINCT member_id) as total')
            ->whereHas('member', function($query) use ($userGroup) {
                $query->where('group', $userGroup);
            })
            ->whereBetween('date', [$dateDebut, $dateFin])
            ->groupBy('jour')
            ->orderBy('jour')
            ->get();

        // Taux de présence par membre
        $membresStats = Member::where('group', $userGroup)
            ->withCount(['presences as total_presences' => function($query) use ($dateDebut, $dateFin) {
                $query->whereBetween('date', [$dateDebut, $dateFin]);
            }])
            ->get()
            ->map(function($member) use ($periode) {
                $tauxPresence = $periode > 0 ? round(($member->total_presences / $periode) * 100, 1) : 0;
                return [
                    'name' => $member->name,
                    'total_presences' => $member->total_presences,
                    'taux_presence' => $tauxPresence
                ];
            })
            ->sortByDesc('taux_presence');

        // Tendance (comparaison avec période précédente)
        $periodePrec = now()->subDays($periode * 2)->toDateString();
        $dateDebutPrec = $periodePrec;
        $dateFinPrec = now()->subDays($periode)->toDateString();
        
        $presencesActuelles = Presence::whereHas('member', function($query) use ($userGroup) {
                $query->where('group', $userGroup);
            })
            ->whereBetween('date', [$dateDebut, $dateFin])
            ->count();
            
        $presencesPrecedentes = Presence::whereHas('member', function($query) use ($userGroup) {
                $query->where('group', $userGroup);
            })
            ->whereBetween('date', [$dateDebutPrec, $dateFinPrec])
            ->count();
            
        $tendance = $presencesPrecedentes > 0 ? 
            round((($presencesActuelles - $presencesPrecedentes) / $presencesPrecedentes) * 100, 1) : 0;

        return view('statistiques-avancees', compact(
            'totalMembres', 'presencesParJour', 'membresStats', 'tendance', 
            'periode', 'presencesActuelles', 'presencesPrecedentes'
        ));
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

    public function comparaisonPeriodes(Request $request)
    {
        $userGroup = Auth::user()->group;
        $type = $request->input('type', 'mois'); // mois, semaine, annee
        
        $dateActuelle = now();
        
        switch($type) {
            case 'semaine':
                $debutActuel = $dateActuelle->startOfWeek()->toDateString();
                $finActuel = $dateActuelle->endOfWeek()->toDateString();
                $debutPrecedent = $dateActuelle->subWeek()->startOfWeek()->toDateString();
                $finPrecedent = $dateActuelle->endOfWeek()->toDateString();
                break;
            case 'annee':
                $debutActuel = $dateActuelle->startOfYear()->toDateString();
                $finActuel = $dateActuelle->endOfYear()->toDateString();
                $debutPrecedent = $dateActuelle->subYear()->startOfYear()->toDateString();
                $finPrecedent = $dateActuelle->endOfYear()->toDateString();
                break;
            default: // mois
                $debutActuel = $dateActuelle->startOfMonth()->toDateString();
                $finActuel = $dateActuelle->endOfMonth()->toDateString();
                $debutPrecedent = $dateActuelle->subMonth()->startOfMonth()->toDateString();
                $finPrecedent = $dateActuelle->endOfMonth()->toDateString();
        }
        
        $presencesActuelles = Presence::whereHas('member', function($query) use ($userGroup) {
                $query->where('group', $userGroup);
            })
            ->whereBetween('date', [$debutActuel, $finActuel])
            ->count();
            
        $presencesPrecedentes = Presence::whereHas('member', function($query) use ($userGroup) {
                $query->where('group', $userGroup);
            })
            ->whereBetween('date', [$debutPrecedent, $finPrecedent])
            ->count();
            
        $evolution = $presencesPrecedentes > 0 ? 
            round((($presencesActuelles - $presencesPrecedentes) / $presencesPrecedentes) * 100, 1) : 0;
            
        $totalMembres = Member::where('group', $userGroup)->count();
        $tauxActuel = $totalMembres > 0 ? round(($presencesActuelles / $totalMembres) * 100, 1) : 0;
        $tauxPrecedent = $totalMembres > 0 ? round(($presencesPrecedentes / $totalMembres) * 100, 1) : 0;
        
        return view('comparaison-periodes', compact(
            'type', 'presencesActuelles', 'presencesPrecedentes', 'evolution',
            'tauxActuel', 'tauxPrecedent', 'debutActuel', 'finActuel'
        ));
    }
}
