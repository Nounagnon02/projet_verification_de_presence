<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Génération automatique des événements depuis l'emploi du temps (chaque jour à minuit)
Artisan::command('schedule:generate-events', function () {
    $this->call('events:generate-from-schedule', ['--days' => 14]);
})->purpose('Génère les événements pour les 14 prochains jours depuis l\'emploi du temps')
  ->dailyAt('00:05');

// Génération automatique des QR codes 15 min avant la fin des cours (toutes les minutes)
Artisan::command('schedule:generate-qrcodes', function () {
    $this->call('qrcode:auto-generate');
})->purpose('Génère les QR codes 15 min avant la fin des cours du jour')
  ->everyMinute();
