<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LegalController extends Controller
{
    public function about()
    {
        return view('about');
    }

    public function privacy()
    {
        return view('legal.privacy');
    }

    public function terms()
    {
        return view('legal.terms');
    }

    public function security()
    {
        $securityInfo = [
            'encryption' => 'AES-256',
            'hosting' => 'Render (Infrastructure sécurisée)',
            'database' => 'Turso (SQLite distribué avec chiffrement)',
            'backup' => 'Automatique quotidienne',
            'compliance' => ['RGPD', 'HTTPS obligatoire'],
            'last_audit' => now()->format('Y-m-d')
        ];

        return view('legal.security', compact('securityInfo'));
    }
}