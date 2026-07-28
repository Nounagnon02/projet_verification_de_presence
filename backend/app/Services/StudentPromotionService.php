<?php

namespace App\Services;

use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use Illuminate\Support\Facades\DB;

/**
 * Promotion d'une promotion d'étudiants d'une filière vers une autre
 * (ex. IM-L1 → IM-L2), avec recalcul des inscriptions aux ECs.
 *
 * Logique métier partagée entre la commande artisan students:promote et
 * l'endpoint d'administration, pour qu'elles ne divergent jamais.
 *
 * L'identifiant_unique n'est volontairement PAS recalculé : c'est le login
 * permanent de l'étudiant, il ne doit pas changer d'une année à l'autre.
 */
class StudentPromotionService
{
    /**
     * Nombre d'étudiants concernés par une promotion depuis cette filière.
     */
    public function countEligible(Filiere $from): int
    {
        return Etudiant::where('filiere_id', $from->id)->count();
    }

    /**
     * Exécute la promotion dans une transaction.
     *
     * @return int Nombre d'étudiants promus.
     */
    public function promote(Filiere $from, Filiere $to, ?AnneeAcademique $toAnnee = null): int
    {
        return DB::transaction(function () use ($from, $to, $toAnnee) {
            $promoted = 0;

            Etudiant::where('filiere_id', $from->id)
                ->chunkById(100, function ($students) use ($to, $toAnnee, &$promoted) {
                    foreach ($students as $student) {
                        $student->filiere_id = $to->id;

                        if ($toAnnee) {
                            $student->annee_id = $toAnnee->id;
                        }

                        $student->save();

                        // Ré-inscrit l'étudiant aux ECs de sa nouvelle filière
                        // et année (détache les anciennes, rattache les
                        // nouvelles). N'affecte pas l'historique de présence.
                        $student->recalculateEnrollments();

                        $promoted++;
                    }
                });

            return $promoted;
        });
    }
}
