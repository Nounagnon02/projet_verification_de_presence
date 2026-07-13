<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\CleanExpiredQrCodes::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Nettoyage quotidien des QR codes expirés à 2h du matin
        $schedule->command('qr:clean-expired --force')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/qr-cleanup.log'));

        // Nettoyage des sessions expirées (Laravel par défaut)
        // $schedule->command('session:gc')->daily();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}