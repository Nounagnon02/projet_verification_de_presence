<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\QrCode;
use App\Services\QrCodeImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consultation du QR Code du cours par l'étudiant responsable (délégué).
 *
 * Ce contrôleur est volontairement en LECTURE SEULE : le délégué affiche et
 * partage le code que le système a produit, il ne déclenche jamais sa
 * génération. Aucune méthode d'écriture n'existe ici — la restriction est
 * structurelle et non déclarative.
 */
class StudentQrCodeController extends Controller
{
    /**
     * QR Code actif du cours en séance pour la promotion du délégué.
     *
     * GET /api/student/qrcode/current
     */
    public function current(Request $request, QrCodeImageService $images): JsonResponse
    {
        $etudiant = $request->user();

        if (!$etudiant instanceof Etudiant) {
            return $this->errorResponse('Réservé aux comptes étudiants.', 403);
        }

        if (!$etudiant->est_responsable) {
            return $this->forbiddenResponse(
                "Cette fonction est réservée à l'étudiant responsable de la promotion."
            );
        }

        $maintenant = now();
        $visibleAvantFin = (int) config('presence.qr.visible_delegue_avant_fin');

        // Les ECs auxquels l'étudiant est inscrit délimitent sa promotion : le
        // cloisonnement découle de la table pivot, sans filtre supplémentaire à
        // maintenir.
        $ecIds = $etudiant->ecs()->pluck('ecs.id');

        if ($ecIds->isEmpty()) {
            return $this->notFoundResponse("Aucun cours n'est rattaché à votre inscription.");
        }

        // Fenêtre élargie d'un jour en amont pour les séances à cheval sur minuit.
        $evenement = Evenement::with(['ec', 'salleRef'])
            ->whereIn('ec_id', $ecIds)
            ->whereIn('statut', ['planifie', 'en_cours'])
            ->whereBetween('date', [
                $maintenant->copy()->subDay()->toDateString(),
                $maintenant->toDateString(),
            ])
            ->orderBy('heure_debut')
            ->get()
            ->first(function (Evenement $evenement) use ($maintenant, $visibleAvantFin) {
                $ouverture = $evenement->finCours()->subMinutes($visibleAvantFin);

                return $maintenant->betweenIncluded($ouverture, $evenement->fermetureScan());
            });

        if (!$evenement) {
            return $this->notFoundResponse(sprintf(
                "Aucun cours en cours de validation. Le QR Code apparaît %d minutes avant la fin de la séance.",
                $visibleAvantFin
            ));
        }

        $qrCode = QrCode::where('evenement_id', $evenement->id)
            ->where('actif', true)
            ->where('expire_at', '>', $maintenant)
            ->latest('id')
            ->first();

        if (!$qrCode) {
            return $this->notFoundResponse(
                "Le QR Code de cette séance n'est pas encore disponible. Réessayez dans un instant."
            );
        }

        return $this->successResponse([
            'token'       => $qrCode->token,
            'expire_at'   => $qrCode->expire_at?->toIso8601String(),
            'expires_in'  => max(0, $maintenant->diffInSeconds($qrCode->expire_at, false)),
            // Image fournie par le serveur : l'application mobile n'a aucune
            // bibliothèque de rendu QR à embarquer, et le token ne sort pas du
            // système pour être transformé en image.
            'svg'         => $images->svg($qrCode->token),
            'url'         => $images->urlValidation($qrCode->token),
            'evenement'   => [
                'id'          => $evenement->id,
                'cours'       => $evenement->ec?->intitule,
                'code'        => $evenement->ec?->code,
                'salle'       => $evenement->salleRef?->nom ?? $evenement->salle,
                'heure_debut' => substr((string) $evenement->heure_debut, 0, 5),
                'heure_fin'   => substr((string) $evenement->heure_fin, 0, 5),
                'ferme_a'     => $evenement->fermetureScan()->format('H:i'),
            ],
        ], 'QR Code du cours récupéré.');
    }
}
