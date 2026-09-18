<?php

declare(strict_types=1);

namespace App\Services\Presence;

use App\Models\Anomaly;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Presence;

/**
 * Détection d'appareil partagé (« buddy punching ») entre étudiants distincts.
 *
 * Un même appareil ayant déjà servi à un autre étudiant pour cette séance
 * bascule TOUTES les présences déjà enregistrées depuis cet appareil — la
 * nouvelle et les précédentes — en « suspect », le premier scan compris : il
 * est souvent celui du propriétaire, qui pointe ensuite pour les autres.
 */
final class AppareilPartage
{
    public static function arbitrer(Evenement $evenement, Etudiant $etudiant, string $empreinte): string
    {
        // « exists » plutôt que « count(distinct) » : dans le cas nominal —
        // aucun appareil partagé — la question posée est binaire, et un COUNT
        // DISTINCT parcourt toutes les présences de l'événement pour rien.
        $memeAppareil = Presence::where('evenement_id', $evenement->id)
            ->where('device_fingerprint', $empreinte)
            ->where('etudiant_id', '!=', $etudiant->id);

        if (!$memeAppareil->exists()) {
            return 'valide';
        }

        $autresEtudiantsMemeAppareil = (clone $memeAppareil)->distinct()->count('etudiant_id');

        // Une présence déjà arbitrée par un administrateur n'est pas rouverte.
        (clone $memeAppareil)
            ->where('statut', 'valide')
            ->whereNull('validated_by')
            ->update(['statut' => 'suspect']);

        Anomaly::create([
            'etudiant_id' => $etudiant->id,
            'type'        => 'appareil_partage',
            'description' => "Appareil partagé suspecté : le même appareil a déjà servi à "
                . "{$autresEtudiantsMemeAppareil} autre(s) étudiant(s) pour l'événement "
                . "#{$evenement->id}. Scan de {$etudiant->nom} {$etudiant->prenom} marqué à vérifier.",
            'severity'    => 'high',
            'metadata'    => [
                'device_fingerprint'          => $empreinte,
                'evenement_id'                => $evenement->id,
                'autres_etudiants_meme_device' => $autresEtudiantsMemeAppareil,
            ],
        ]);

        return 'suspect';
    }
}
