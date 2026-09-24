<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Formats de date et d'heure
    |--------------------------------------------------------------------------
    |
    | Ces chaînes sont des formats PHP passés à Carbon::translatedFormat(), qui
    | traduit les noms de jours et de mois selon la locale Carbon posée par
    | App\Http\Middleware\SetLocale. Seul l'ORDRE des éléments change ici : le
    | français écrit 24/09/2026 là où l'anglais écrit 09/24/2026.
    |
    | À ne pas utiliser pour les formats techniques ('Y-m-d' servant de clé de
    | tableau ou de comparaison) : ceux-là doivent rester figés.
    |
    */

    'short' => 'd/m/Y',
    'long' => 'l j F Y',
    'day_month' => 'l j F',
    'weekday_short' => 'D',
    'time' => 'H:i',
    'datetime' => 'd/m/Y à H:i',

];
