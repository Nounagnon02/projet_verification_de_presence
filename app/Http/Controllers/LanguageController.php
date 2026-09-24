<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        // Un lien périmé vers une langue retirée ne doit pas produire d'erreur :
        // on renvoie l'utilisateur d'où il vient sans rien changer.
        if (! array_key_exists($locale, config('locales.supported', []))) {
            return redirect()->back();
        }

        $request->session()->put(SetLocale::COOKIE, $locale);

        // Sur un compte, la préférence suit l'utilisateur d'un appareil à l'autre.
        if ($user = $request->user()) {
            $user->update(['locale' => $locale]);
        }

        // Le cookie sert aux visiteurs non connectés, d'une visite à l'autre.
        return redirect()->back()->withCookie(
            cookie(SetLocale::COOKIE, $locale, SetLocale::COOKIE_MINUTES)
        );
    }
}
