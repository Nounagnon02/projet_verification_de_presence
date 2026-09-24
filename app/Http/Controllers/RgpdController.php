<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RgpdController extends Controller
{
    public function index()
    {
        $membres = Member::ledBy(Auth::user())
            ->with('groups')
            ->orderBy('name')
            ->get();

        return view('rgpd.index', compact('membres'));
    }

    public function consent(Request $request)
    {
        $user = Auth::user();
        $user->update([
            'gdpr_consent' => true,
            'gdpr_consent_at' => now()
        ]);

        return redirect()->back()->with('success', __('Consentement RGPD enregistré.'));
    }

    public function withdraw()
    {
        $user = Auth::user();
        $user->update([
            'gdpr_consent' => false,
            'gdpr_consent_at' => null
        ]);

        return redirect()->back()->with('success', __('Consentement RGPD retiré.'));
    }
}