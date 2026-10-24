<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ParametreGlobalResource;
use App\Models\ParametreGlobal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Réglages anti-fraude et de scan de l'université (écran « Paramètres » du
 * super admin). Une seule ligne, jamais créée ni supprimée ici — la migration
 * s'en est chargée une fois pour toutes.
 */
class ParametresGlobauxController extends Controller
{
    /** GET /api/super-admin/parametres */
    public function index(): JsonResponse
    {
        return $this->successResponse(new ParametreGlobalResource(ParametreGlobal::actuel()));
    }

    /**
     * Les bornes ci-dessous ne sont pas arbitraires : chacune rejette une
     * valeur qui a un effet documenté et mesuré (voir la migration qui crée
     * cette table). Les assouplir reviendrait à permettre, depuis cet écran,
     * de recréer un incident déjà survenu.
     *
     * PUT /api/super-admin/parametres
     */
    public function update(Request $request): JsonResponse
    {
        $valeurs = $request->validate([
            'scan_minutes_avant_fin' => 'required|integer|min:1|max:120',
            'scan_minutes_apres_fin' => 'required|integer|min:0|max:60',
            // En dessous de 30 s, aucun effet réel : le planificateur qui fait
            // tourner le jeton ne s'exécute qu'une fois par minute.
            'qr_ttl_secondes' => 'required|integer|min:30|max:300',
            'qr_visible_delegue_avant_fin' => 'required|integer|min:1|max:60',
            'seance_duree_max_heures' => 'required|numeric|min:0.5|max:12',
            // La règle anti-fraude (CDC 9.2.4) : trop haut, elle cesse de
            // protéger ; à zéro, elle bloque tout scan.
            'limite_scan_par_etudiant' => 'required|integer|min:1|max:20',
            // Filet anti-inondation, pas une règle de fraude : doit rester très
            // au-dessus de l'hypothèse H3 (500 scans simultanés). Mesuré : à
            // 600, une salle qui l'atteint derrière une IP partagée (NAT,
            // CGNAT) se voyait déjà refuser des scans légitimes.
            'limite_scan_par_ip' => 'required|integer|min:1000|max:100000',
        ], [
            'limite_scan_par_ip.min' => "Ce plafond protège contre l'inondation, pas contre la fraude : en dessous de 1000, une salle ou un campus derrière une même adresse IP (réseau partagé, CGNAT) se verrait refuser des scans légitimes.",
            'qr_ttl_secondes.min' => 'En dessous de 30 secondes, cette durée n\'a plus d\'effet réel : le jeton tourne au mieux une fois par minute.',
        ]);

        $parametres = ParametreGlobal::actuel();
        $parametres->update($valeurs);

        return $this->successResponse(
            new ParametreGlobalResource($parametres->fresh()),
            'Réglages mis à jour.'
        );
    }
}
