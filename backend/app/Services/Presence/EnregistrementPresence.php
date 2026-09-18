<?php

declare(strict_types=1);

namespace App\Services\Presence;

use App\Models\Anomaly;
use App\Models\Evenement;
use App\Models\Presence;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Orchestrateur du scan de présence.
 *
 * Remplace PresenceController::scan(), qui faisait 351 lignes et enchaînait
 * dix vérifications numérotées en commentaire, chacune construisant elle-même
 * sa réponse HTTP : aucune ne pouvait être exercée sans monter une requête
 * complète. Chaque étape vit désormais dans sa propre classe, testable seule
 * (voir tests/Unit/Presence/), et le contrôleur ne fait plus que traduire le
 * ResultatScan en réponse.
 */
final class EnregistrementPresence
{
    public function __construct(
        private readonly JetonQrCode $jetonQrCode = new JetonQrCode(),
    ) {
    }

    public function traiter(DemandeDeScan $demande): ResultatScan
    {
        $qrCode = $this->jetonQrCode->resoudre($demande->jeton);
        if (!$qrCode) {
            return ResultatScan::refus(JetonQrCode::REFUS, 410);
        }

        // Le jeton n'est PAS invalidé ici : voir le docblock de JetonQrCode.
        // Il reste valable pour toute la salle pendant sa fenêtre de 60 s ; la
        // rotation appartient au seul planificateur (qrcode:auto-generate).
        $evenement = $qrCode->evenement;
        $maintenant = Carbon::now();

        $fenetre = FenetreDePresence::verifier($evenement, $maintenant);
        if (!$fenetre->satisfait) {
            return ResultatScan::depuisVerdict($fenetre);
        }

        $inscription = InscriptionAuCours::verifier($demande->etudiant, $evenement);
        if (!$inscription->satisfait) {
            return ResultatScan::depuisVerdict($inscription);
        }

        $refusVerification = $this->verifierSalle($demande, $evenement);
        if ($refusVerification !== null) {
            return ResultatScan::depuisVerdict($refusVerification);
        }

        return $this->enregistrer($demande, $evenement, $maintenant);
    }

    /**
     * Géolocalisation et réseau, si la salle les exige. Une salle absente ou
     * inactive retombe en mode basique : le QR Code seul fait foi.
     *
     * Les deux facteurs sont vérifiés ensemble et journalisés dans UNE seule
     * anomalie : un étudiant hors zone ET hors réseau doit lire les deux
     * motifs à la fois, pas deux refus successifs qu'il croirait liés au même
     * problème.
     */
    private function verifierSalle(DemandeDeScan $demande, Evenement $evenement): ?Verdict
    {
        $salle = $evenement->salleRef;
        if (!$salle || !$salle->actif) {
            return null;
        }

        $geo = GeoReperage::verifier($salle, $demande->latitude, $demande->longitude);
        $reseau = FacteurReseau::verifier($salle, $demande->ssid, $demande->bssid, $demande->ip);

        /** @var list<Verdict> $echecs */
        $echecs = array_values(array_filter(
            [$geo, $reseau],
            fn (?Verdict $v) => $v !== null && !$v->satisfait,
        ));

        if ($echecs === []) {
            return null;
        }

        $detail = array_merge(...array_map(fn (Verdict $v) => $v->detail, $echecs));

        Anomaly::create([
            'etudiant_id' => $demande->etudiant->id,
            'type'        => 'verification_echouee',
            'description' => "Vérification localisation/réseau échouée pour "
                . "{$demande->etudiant->nom} {$demande->etudiant->prenom} — séance #{$evenement->id}.",
            'severity'    => 'medium',
            'metadata'    => $detail + ['evenement_id' => $evenement->id],
        ]);

        $message = 'Vérification de présence échouée. Vous devez être physiquement dans la salle de cours. '
            . implode(' ', array_map(fn (Verdict $v) => $v->messageEtudiant, $echecs));

        return Verdict::refus($message, 403, $detail);
    }

    /**
     * Double scan, appareil partagé, écriture de la présence.
     *
     * « withTrashed » est indispensable : la contrainte d'unicité SQL porte
     * sur (etudiant_id, evenement_id) sans tenir compte de deleted_at, alors
     * qu'Eloquent exclut par défaut les lignes supprimées. Une présence
     * effacée par un administrateur reste sinon invisible à ce contrôle tout
     * en bloquant l'insertion.
     */
    private function enregistrer(DemandeDeScan $demande, Evenement $evenement, Carbon $maintenant): ResultatScan
    {
        $etudiant = $demande->etudiant;

        $existante = Presence::withTrashed()
            ->where('etudiant_id', $etudiant->id)
            ->where('evenement_id', $evenement->id)
            ->first();

        // Présence supprimée par un administrateur : le scan la rétablit avec
        // les données du nouveau passage plutôt que de priver l'étudiant de
        // toute nouvelle tentative pour ce cours.
        if ($existante && $existante->trashed()) {
            $statut = AppareilPartage::arbitrer($evenement, $etudiant, $demande->empreinteAppareil);

            $existante->restore();
            $existante->update([
                'heure_scan'         => $maintenant,
                'device_fingerprint' => $demande->empreinteAppareil,
                'ip_address'         => $demande->ip,
                'statut'             => $statut,
                'validated_by'       => null,
                'validated_at'       => null,
                'validation_motif'   => null,
                'latitude'           => $demande->latitude,
                'longitude'          => $demande->longitude,
            ]);

            return ResultatScan::enregistre($this->donneesReponse($etudiant, $evenement, $maintenant), 200);
        }

        if ($existante) {
            if ($existante->device_fingerprint !== $demande->empreinteAppareil) {
                Anomaly::create([
                    'etudiant_id' => $etudiant->id,
                    'type'        => 'double_scan_device_mismatch',
                    'description' => "Fraude suspectée : l'étudiant {$etudiant->nom} {$etudiant->prenom} "
                        . "a déjà scanné l'événement #{$evenement->id} avec un appareil différent.",
                    'severity'    => 'high',
                    'metadata'    => [
                        'premier_device'       => $existante->device_fingerprint,
                        'nouveau_device'       => $demande->empreinteAppareil,
                        'premiere_presence_id' => $existante->id,
                        'evenement_id'         => $evenement->id,
                    ],
                ]);

                return ResultatScan::refus('Alerte fraude : présence déjà enregistrée depuis un autre appareil.', 409);
            }

            return ResultatScan::refus('Présence déjà enregistrée.', 409);
        }

        // Détection d'appareil partagé AVANT l'écriture : elle-même journalise
        // son anomalie et bascule les présences déjà en place, cette écriture
        // ne fait que porter le statut qui en résulte pour CE scan.
        $statut = AppareilPartage::arbitrer($evenement, $etudiant, $demande->empreinteAppareil);

        // La contrainte d'unicité est le dernier rempart contre deux scans
        // concurrents du même étudiant. Sans ce filet, sa violation remontait
        // telle quelle au client : l'exception PDO expose le nom de la base,
        // l'hôte, le port et les identifiants internes.
        try {
            \App\Models\Presence::create([
                'etudiant_id'        => $etudiant->id,
                'evenement_id'       => $evenement->id,
                'heure_scan'         => $maintenant,
                'device_fingerprint' => $demande->empreinteAppareil,
                'ip_address'         => $demande->ip,
                'statut'             => $statut,
                'latitude'           => $demande->latitude,
                'longitude'          => $demande->longitude,
            ]);
        } catch (UniqueConstraintViolationException) {
            return ResultatScan::refus('Présence déjà enregistrée.', 409);
        }

        return ResultatScan::enregistre($this->donneesReponse($etudiant, $evenement, $maintenant));
    }

    /**
     * @return array<string, mixed>
     */
    private function donneesReponse(\App\Models\Etudiant $etudiant, Evenement $evenement, Carbon $maintenant): array
    {
        return [
            'etudiant'  => "{$etudiant->nom} {$etudiant->prenom}",
            'matricule' => $etudiant->matricule,
            'heure'     => $maintenant->format('H:i:s'),
            'cours'     => $evenement->ec?->intitule ?? 'Cours',
        ];
    }
}
