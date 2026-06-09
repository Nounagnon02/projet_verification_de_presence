<?php

namespace Database\Seeders;

use App\Models\Evenement;
use App\Models\QrCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class QrCodeSeeder extends Seeder
{
    public function run(): void
    {
        // Créer des QR codes pour les événements à venir (planifiés)
        $evenementsFuturs = Evenement::whereIn('statut', ['planifie', 'en_cours'])
            ->inRandomOrder()
            ->take(10)
            ->get();

        $count = 0;

        foreach ($evenementsFuturs as $evenement) {
            // Désactiver les anciens QR codes
            QrCode::where('evenement_id', $evenement->id)
                ->where('actif', true)
                ->update(['actif' => false]);

            // Créer un QR code valide pour 60 minutes (pour la démo)
            QrCode::create([
                'evenement_id' => $evenement->id,
                'token'        => Str::uuid()->toString(),
                'expire_at'    => now()->addHours(2),
                'actif'        => true,
            ]);
            $count++;
        }

        // Créer des QR codes expirés pour quelques événements passés
        $evenementsPasses = Evenement::where('statut', 'termine')
            ->inRandomOrder()
            ->take(5)
            ->get();

        foreach ($evenementsPasses as $evenement) {
            QrCode::create([
                'evenement_id' => $evenement->id,
                'token'        => Str::uuid()->toString(),
                'expire_at'    => now()->subHours(mt_rand(2, 48)),
                'actif'        => false,
            ]);
            $count++;
        }

        $this->command->info("QR codes créés : {$count}");
    }
}
