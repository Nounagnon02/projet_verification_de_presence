<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Traitement de la file d'attente par le planificateur.
// Le plan gratuit Render ne maintient pas de worker permanent (un worker n'a
// pas d'endpoint HTTP pour être réveillé). Le cron `schedule:run` tourne, lui,
// chaque minute : on l'utilise pour drainer la queue. Les jobs asynchrones
// (import IA Gemini, envoi d'identifiants…) sont ainsi traités en < 1 min
// sans worker dédié. --stop-when-empty : le process se termine dès la file
// vide ; --max-time borne la durée pour rester sous la minute.
// Toutes les queues réellement utilisées, par ordre de priorité. L'ancien
// worker n'écoutait que « gemini-import,default » : les jobs « high » et
// « ai-import » (import IA des cours) n'étaient jamais traités.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3 --queue=high,ai-import,gemini-import,default')
    ->everyMinute()
    ->withoutOverlapping();

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
