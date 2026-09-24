<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Formatos de fecha y hora
    |--------------------------------------------------------------------------
    |
    | Cadenas de formato PHP para Carbon::translatedFormat(). Solo cambia aquí
    | el ORDEN de los elementos; los nombres de días y meses los traduce Carbon.
    | No usar para formatos técnicos como 'Y-m-d' (claves y comparaciones).
    |
    */

    'short' => 'd/m/Y',
    'long' => 'l j \d\e F \d\e Y',
    'day_month' => 'l j \d\e F',
    'weekday_short' => 'D',
    'time' => 'H:i',
    'datetime' => 'd/m/Y \a \l\a\s H:i',

];
