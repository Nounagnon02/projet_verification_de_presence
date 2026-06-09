<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etudiant;
use App\Models\Filiere;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EtudiantEcSeeder extends Seeder
{
    public function run(): void
    {
        $activeAnnee = AnneeAcademique::where('active', true)->first()->id;
        $filieres = Filiere::all()->keyBy('code');

        $total = 0;

        foreach ($filieres as $filiere) {
            // Récupérer les étudiants de cette filière
            $etudiants = Etudiant::where('filiere_id', $filiere->id)->get();
            if ($etudiants->isEmpty()) continue;

            // Récupérer tous les ECs des UEs de cette filière
            $ecs = Ec::whereHas('ue', function ($q) use ($filiere) {
                $q->where('filiere_id', $filiere->id);
            })->get();

            if ($ecs->isEmpty()) continue;

            // Enrôler chaque étudiant dans tous les ECs de sa filière
            foreach ($etudiants as $etudiant) {
                foreach ($ecs as $ec) {
                    DB::table('etudiant_ec')->insertOrIgnore([
                        'etudiant_id' => $etudiant->id,
                        'ec_id'       => $ec->id,
                        'annee_id'    => $activeAnnee,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                    $total++;
                }
            }
        }

        $this->command->info("Inscriptions étudiant-EC créées : {$total}");
    }
}
