<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RgpdController extends Controller
{
    public function index()
    {
        return view('rgpd.index');
    }

    public function consent(Request $request)
    {
        $user = Auth::user();
        $user->update([
            'gdpr_consent' => true,
            'gdpr_consent_at' => now()
        ]);

        return redirect()->back()->with('success', 'Consentement RGPD enregistré');
    }

    public function withdraw()
    {
        $user = Auth::user();
        $user->update([
            'gdpr_consent' => false,
            'gdpr_consent_at' => null
        ]);

        return redirect()->back()->with('success', 'Consentement RGPD retiré');
    }
}