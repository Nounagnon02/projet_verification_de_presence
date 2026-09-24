<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Langues prises en charge
    |--------------------------------------------------------------------------
    |
    | Source unique de vérité pour les langues de l'application. Avant, la
    | liste ['fr','en','es'] était recopiée dans SetLocale et LanguageController.
    |
    | Clés de chaque entrée :
    |   native   nom de la langue dans la langue elle-même (affiché au visiteur)
    |   label    nom de la langue en français (usage interne, admin)
    |   carbon   locale à donner à Carbon pour les dates ; le fon n'existe pas
    |            dans Carbon (823 locales, pas de « fon ») -> repli sur « fr »
    |   glyphs   'basic'    : la police Atkinson Hyperlegible suffit
    |            'extended' : la langue a besoin de caractères qu'Atkinson
    |                         Hyperlegible ne contient pas (ẹ U+1EB9, ọ U+1ECD,
    |                         tons U+0300/U+0301) -> bascule sur Noto Sans,
    |                         voir resources/views/components/fonts.blade.php
    |   ready    true quand la traduction est relue et peut être proposée dans
    |            le sélecteur de langue. Une langue non « ready » reste
    |            accessible par URL mais n'est pas offerte au visiteur.
    |
    */

    'supported' => [

        'fr' => [
            'native' => 'Français',
            'label' => 'Français',
            'carbon' => 'fr',
            'glyphs' => 'basic',
            'ready' => true,
        ],

        'en' => [
            'native' => 'English',
            'label' => 'Anglais',
            'carbon' => 'en',
            'glyphs' => 'basic',
            'ready' => true,
        ],

        'es' => [
            'native' => 'Español',
            'label' => 'Espagnol',
            'carbon' => 'es',
            'glyphs' => 'basic',
            'ready' => true,
        ],

        // Yoruba : ISO 639-1 « yo », présent dans CLDR et dans Carbon
        // (yo, yo_NG, yo_BJ). Une seule forme de pluriel (catégorie « other »).
        // Brouillon rédigé sans relecture par un locuteur : ready reste false,
        // la langue n'est donc ni proposée ni servie. Voir lang/README.md.
        'yo' => [
            'native' => 'Yorùbá',
            'label' => 'Yoruba',
            'carbon' => 'yo',
            'glyphs' => 'extended',
            'ready' => false,
        ],

        // Fon : ISO 639-3 « fon », aucun code à deux lettres. Absent de CLDR
        // et de Carbon : les dates sont rendues en français.
        'fon' => [
            'native' => 'Fɔ̀ngbè',
            'label' => 'Fon',
            'carbon' => 'fr',
            'glyphs' => 'extended',
            'ready' => false,
        ],

    ],

];
