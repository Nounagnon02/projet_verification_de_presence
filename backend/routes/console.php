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

// Rotation des QR codes des cours dont la fenêtre de présence est ouverte.
// Cette fréquence EST la cadence de rotation réelle des tokens : la durée de vie
// configurée (presence.qr.ttl_secondes) ne peut pas descendre en dessous d'elle.
Artisan::command('schedule:generate-qrcodes', function () {
    $this->call('qrcode:auto-generate');
})->purpose('Fait tourner les QR codes des cours en fenêtre de présence')
  ->everyMinute();

// Clôture des séances dont la fenêtre de présence est fermée. Sans elle, rien
// ne passait un événement à « terminé » : le tableau de bord, le taux de
// présence et l'avancement des EC (calculé à la lecture), qui ne comptent que les
// séances terminées, dépendaient d'une saisie manuelle.
Schedule::command('events:close-finished')
    ->everyMinute()
    ->withoutOverlapping();

// Les deux taches ci-dessous etaient declarees dans app/Console/Kernel.php.
// Ce fichier n'est plus charge depuis Laravel 11 (le planificateur vit ici et
// dans bootstrap/app.php) : elles ne tournaient donc pas du tout, en silence.
// « schedule:list » ne les listait pas davantage.

// Nettoyage des QR codes expires depuis plus de 30 jours.
Schedule::command('qr:clean-expired --force')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/qr-cleanup.log'));

