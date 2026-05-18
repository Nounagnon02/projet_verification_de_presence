<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    public function switch($locale)
    {
        if (in_array($locale, ['fr', 'en', 'es'])) {
            Session::put('locale', $locale);
        }
        
        return redirect()->back();
    }
}