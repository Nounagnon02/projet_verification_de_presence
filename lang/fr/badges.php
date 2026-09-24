<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Badges
    |--------------------------------------------------------------------------
    |
    | Indexés sur la colonne badges.condition, qui est une clé technique stable.
    | Les colonnes name/description de la base gardent le libellé français créé
    | par BadgeService::createDefaultBadges() et servent de repli.
    |
    */

    'first_presence' => [
        'name' => 'Première présence',
        'description' => 'Votre première présence enregistrée',
    ],
    'streak_7' => [
        'name' => 'Série 7 jours',
        'description' => '7 jours de présence consécutifs',
    ],
    'streak_14' => [
        'name' => 'Série 14 jours',
        'description' => '14 jours de présence consécutifs',
    ],
    'streak_30' => [
        'name' => 'Série 30 jours',
        'description' => '30 jours de présence consécutifs',
    ],
    'perfect_month' => [
        'name' => 'Mois parfait',
        'description' => '100 % de présence sur un mois complet',
    ],
    'early_bird' => [
        'name' => 'Lève-tôt',
        'description' => '10 présences avant 9 h',
    ],
    'regular_10' => [
        'name' => 'Régulier (10)',
        'description' => '10 présences enregistrées',
    ],
    'regular_25' => [
        'name' => 'Régulier (25)',
        'description' => '25 présences enregistrées',
    ],
    'regular_50' => [
        'name' => 'Régulier (50)',
        'description' => '50 présences enregistrées',
    ],
    'regular_100' => [
        'name' => 'Centenaire',
        'description' => '100 présences enregistrées',
    ],

];
