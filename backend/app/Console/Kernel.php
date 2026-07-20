<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\CleanExpiredQrCodes::class,
        \App\Console\Commands\GenerateEventsFromSchedule::class,
        \App\Console\Commands\AutoGenerateQrCode::class,
        \App\Console\Commands\SyncEcStatus::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Nettoyage quotidien des QR codes expirés à 2h du matin
        $schedule->command('qr:clean-expired --force')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/qr-cleanup.log'));

        // Génération des événements depuis l'emploi du temps (chaque nuit)
        $schedule->command('events:generate-from-schedule --days=14')
            ->dailyAt('00:05')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/events-generate.log'));

        // Génération automatique des QR codes 15 min avant la fin des cours (régénération toutes les 150s)
        $schedule->command('qrcode:auto-generate')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/qrcode-auto.log'));

        // Synchronisation quotidienne des statuts EC/UE (volume horaire vs heures effectuées)
        $schedule->command('ecs:sync-statut')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/ec-sync.log'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}