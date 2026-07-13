<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\QrCode;
use Carbon\Carbon;

class CleanExpiredQrCodes extends Command
{
    protected $signature = 'qr:clean-expired
                            {--dry-run : Afficher ce qui serait supprimé sans le faire}
                            {--force : Forcer la suppression sans confirmation}
                            {--days=30 : Supprimer les QR codes expirés depuis plus de X jours}';

    protected $description = 'Nettoyer les QR codes expirés de la base de données';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $days = (int) $this->option('days');

        $cutoffDate = Carbon::now()->subDays($days);

        // QR codes expirés depuis plus de X jours
        $query = QrCode::where('expire_at', '<', $cutoffDate)
            ->where('actif', false);

        $count = $query->count();

        if ($count === 0) {
            $this->info("Aucun QR code expiré depuis plus de {$days} jours trouvé.");
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Événement ID', 'Token', 'Expiré le', 'Actif'],
            $query->limit(20)->get(['id', 'evenement_id', 'token', 'expire_at', 'actif'])->toArray()
        );

        if ($count > 20) {
            $this->line("... et {$count - 20} autres QR codes");
        }

        if ($dryRun) {
            $this->warn("[DRY RUN] {$count} QR codes seraient supprimés (expirés depuis plus de {$days} jours).");
            return self::SUCCESS;
        }

        if (!$force && !$this->confirm("Supprimer {$count} QR codes expirés depuis plus de {$days} jours ?")) {
            $this->info('Opération annulée.');
            return self::SUCCESS;
        }

        // Suppression par lots pour éviter les timeouts
        $deleted = 0;
        $query->chunkById(100, function ($qrCodes) use (&$deleted) {
            foreach ($qrCodes as $qrCode) {
                $qrCode->delete();
                $deleted++;
            }
        });

        $this->info("✅ {$deleted} QR codes expirés supprimés avec succès.");
        return self::SUCCESS;
    }
}