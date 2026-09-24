<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Date and time formats
    |--------------------------------------------------------------------------
    |
    | PHP format strings passed to Carbon::translatedFormat(). Only the ORDER of
    | the parts differs between languages; day and month names are translated by
    | Carbon itself. Do not use these for technical formats such as 'Y-m-d' used
    | as array keys or for comparisons.
    |
    */

    'short' => 'm/d/Y',
    'long' => 'l, F j, Y',
    'day_month' => 'l, F j',
    'weekday_short' => 'D',
    'time' => 'g:i A',
    'datetime' => 'm/d/Y \a\t g:i A',

];
