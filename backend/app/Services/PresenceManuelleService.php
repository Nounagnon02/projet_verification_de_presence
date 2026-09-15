<?php

namespace App\Services;

use App\Exceptions\PresenceManuelleImpossible;
use App\Models\AuditLog;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Presence;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre une présence à la place d'un scan, par l'administration.
 *
 * Deux chemins y mènent : le rattrapage d'un scan refusé à tort (Scans
 * refusés) et la saisie d'un étudiant qui n'a pas pu scanner (Saisie
 * manuelle). Les mêmes garde-fous valent pour les deux : séance commencée et
 * non annulée, étudiant inscrit au cours, aucune présence déjà enregistrée —
 * celle-là se tranche dans la file de validation. Le motif est obligatoire et
 * l'opération est inscrite au journal d'audit.
 */
class PresenceManuelleService
{
    /**
     * @param  CarbonInterface  $heure  heure de présence retenue
     * @param  string  $action  action inscrite au journal d'audit
     * @param  array<string, mixed>  $trace  précisions ajoutées au journal
     * @param  callable|null  $dansLaTransaction  reçoit la présence, dans la même transaction
     *
     * @throws PresenceManuelleImpossible
     */
    public function enregistrer(
        Etudiant $etudiant,
        Evenement $evenement,
        User $auteur,
        string $motif,
        CarbonInterface $heure,
        string $action,
        array $trace = [],
        ?string $empreinte = null,
        ?callable $dansLaTransaction = null,
    ): Presence {
        if ($evenement->statut === 'annule') {
            throw new PresenceManuelleImpossible('Cette séance a été annulée.');
        }

        if (now()->lt($evenement->debutCours())) {
            throw new PresenceManuelleImpossible("Cette séance n'a pas encore commencé.");
        }

        if (!$etudiant->peutAssisterA($evenement)) {
            throw new PresenceManuelleImpossible("{$etudiant->prenom} {$etudiant->nom} n'est pas inscrit(e) au cours de cette séance.");
        }

        $existante = Presence::withTrashed()
            ->where('etudiant_id', $etudiant->id)
            ->where('evenement_id', $evenement->id)
            ->first();

        // Une présence déjà là se tranche dans la file d'attente, pas ici.
        if ($existante && !$existante->trashed()) {
            throw new PresenceManuelleImpossible(match ($existante->statut) {
                'suspect' => "Une présence suspecte existe déjà pour cette séance : tranchez-la dans la file d'attente.",
                'rejete'  => "La présence de cet étudiant a été rejetée dans la file d'attente pour cette séance.",
                default   => 'Une présence est déjà enregistrée pour cet étudiant à cette séance.',
            }, 409);
        }

        $valeurs = [
            'heure_scan'         => $heure,
            'device_fingerprint' => $empreinte,
            'ip_address'         => null,
            'latitude'           => null,
            'longitude'          => null,
            'statut'             => 'valide',
            'validated_by'       => $auteur->id,
            'validated_at'       => now(),
            'validation_motif'   => $motif,
        ];

        try {
            return DB::transaction(function () use ($existante, $valeurs, $etudiant, $evenement, $auteur, $motif, $action, $trace, $dansLaTransaction) {
                if ($existante) {
                    // Présence supprimée par un administrateur : elle est rétablie,
                    // la contrainte d'unicité interdisant d'en créer une seconde.
                    $existante->restore();
                    $existante->update($valeurs);
                    $presence = $existante;
                } else {
                    $presence = Presence::create($valeurs + [
                        'etudiant_id'  => $etudiant->id,
                        'evenement_id' => $evenement->id,
                    ]);
                }

                AuditLog::create([
                    'action'     => $action,
                    'model_type' => Presence::class,
                    'model_id'   => $presence->id,
                    'user_id'    => $auteur->id,
                    'old_values' => null,
                    'new_values' => ['statut' => 'valide', 'motif' => $motif] + $trace,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                if ($dansLaTransaction) {
                    $dansLaTransaction($presence);
                }

                return $presence;
            });
        } catch (UniqueConstraintViolationException) {
            throw new PresenceManuelleImpossible('Une présence est déjà enregistrée pour cet étudiant à cette séance.', 409);
        }
    }
}
