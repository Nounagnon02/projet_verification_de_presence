<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ec;
use App\Models\Evenement;
use App\Services\RegleSeanceService;
use App\Models\Ue;
use App\Services\Maquette\RegistreMaquette;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EcController extends Controller
{
    use ScopedByEtablissement;

    public function __construct(private readonly RegistreMaquette $maquette) {}

    public function index(Request $request): JsonResponse
    {
        $query = Ec::with(['ue.filiere', 'ue.filieres:id,code,intitule']);

        // Scope par établissement via UE → filière
        $this->scopeViaRelation($query, $request, 'ue.filiere');

        $ecs = $query->orderBy('code')->get();

        // Volume horaire restant, en une seule requête pour toute la liste.
        // Le formulaire d'ajout d'un cours s'en sert pour annoncer le solde et
        // borner l'heure de fin proposée : sans cette donnée, il ne pourrait
        // que laisser saisir un créneau que le serveur refusera ensuite.
        $seances = Evenement::query()
            ->whereIn('ec_id', $ecs->pluck('id'))
            ->where('statut', '!=', 'annule')
            ->get(['ec_id', 'heure_debut', 'heure_fin', 'type_cours', 'groupe_id'])
            ->groupBy('ec_id');

        // Plafond de durée d'une séance : joint à chaque EC plutôt que codé en dur
        // côté client, pour que la valeur reste définie au seul endroit qui fait
        // foi — config/presence.php, que le serveur applique aussi.
        $dureeMax = (float) config('presence.seance.duree_max_heures');

        $ecs->each(function ($ec) use ($seances, $dureeMax) {
            $liste = $seances->get($ec->id, collect());
            $duree = fn ($e) => RegleSeanceService::duree($e->heure_debut, $e->heure_fin);
            $prises = (float) $liste->sum($duree);

            $ec->heures_reservees   = round($prises, 2);
            $ec->heures_restantes   = round(max(0, (float) $ec->volume_horaire - $prises), 2);
            // Par type, réserve TP/TD comprise ; null tant que le volume n'est
            // pas ventilé, seul le total faisant alors foi.
            $ec->heures_restantes_par_type = $ec->volume_a_ventiler ? null : array_map(
                fn ($h) => round($h, 2),
                RegleSeanceService::restantesDepuis($ec, RegleSeanceService::prisDepuis($liste->map(fn ($e) => ['type' => $e->type_cours, 'groupe_id' => $e->groupe_id, 'heures' => $duree($e)])))
            );
            $ec->duree_max_seance   = $dureeMax;
        });

        // Avancement calculé depuis les séances terminées, pour toute la liste.
        app(\App\Services\AvancementCours::class)->appliquer($ecs);

        return $this->successResponse($ecs);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ue_id'          => 'required|exists:ues,id',
            'code'           => 'required|string|max:20',
            'intitule'       => 'required|string|max:255',
            'volume_horaire' => 'nullable|integer|min:1',
            'volume_cm'      => 'nullable|integer|min:0|max:999',
            'volume_td'      => 'nullable|integer|min:0|max:999',
            'volume_tp'      => 'nullable|integer|min:0|max:999',
            'volume_td_tp'   => 'nullable|integer|min:0|max:999',
        ]);
        $this->exigerUnVolume($validated);

        // L'UE doit être de l'établissement : sans ce contrôle, un admin de
        // faculté ajoutait un EC à l'UE d'une autre faculté en visant son id.
        $ue = \App\Models\Ue::with('filiere')->findOrFail($validated['ue_id']);
        $this->authorizeEtablissement($ue, $request, 'filiere');
        $this->refuserSiAnneeClose((int) $ue->annee_id, $request);

        // Unique dans l'année et l'établissement, réutilisable l'année suivante.
        // La règle « unique dans toute la base » rendait tout EC reconduit
        // impossible à modifier.
        $this->maquette->verifierCodeEc($validated['code'], $ue);

        $ec = Ec::create(array_filter($validated, fn ($v) => $v !== null));
        $ec->load('ue.filiere');
        return $this->createdResponse($ec, 'EC créé avec succès.');
    }

    public function update(Request $request, Ec $ec): JsonResponse
    {
        $this->authorizeEtablissement($ec, $request, 'ue.filiere');
        $this->refuserSiAnneeClose($ec->ue?->annee_id, $request);

        $validated = $request->validate([
            'ue_id'          => 'sometimes|exists:ues,id',
            'code'           => 'sometimes|string|max:20',
            'intitule'       => 'sometimes|string|max:255',
            'volume_horaire' => 'sometimes|nullable|integer|min:1',
            'volume_cm'      => 'sometimes|nullable|integer|min:0|max:999',
            'volume_td'      => 'sometimes|nullable|integer|min:0|max:999',
            'volume_tp'      => 'sometimes|nullable|integer|min:0|max:999',
            'volume_td_tp'   => 'sometimes|nullable|integer|min:0|max:999',
        ]);

        if (array_intersect_key($validated, array_flip([...self::VOLUMES, 'volume_horaire'])) !== []) {
            $this->exigerUnVolume($validated, $ec);
        }

        // Changer d'UE : celle visée doit être de l'établissement, et son année
        // ouverte. On ne regardait que l'EC d'origine.
        $ue = $ec->ue;
        if (isset($validated['ue_id']) && (int) $validated['ue_id'] !== (int) $ec->ue_id) {
            $ue = Ue::with('filiere')->findOrFail($validated['ue_id']);
            $this->authorizeEtablissement($ue, $request, 'filiere');
            $this->refuserSiAnneeClose((int) $ue->annee_id, $request);
        }

        $this->maquette->verifierCodeEc($validated['code'] ?? $ec->code, $ue, $ec->id);

        $ec->update(array_filter($validated, fn ($v) => $v !== null));
        $ec->load('ue.filiere');
        return $this->successResponse($ec, 'EC mis à jour.');
    }

    public function destroy(Request $request, Ec $ec): JsonResponse
    {
        $this->authorizeEtablissement($ec, $request, 'ue.filiere');
        $this->refuserSiAnneeClose($ec->ue?->annee_id, $request);
        $this->preventDeleteWithDependencies($ec, [
            'evenements' => 'événement(s)',
            'etudiants'  => 'étudiant(s) inscrit(s)',
        ]);

        $ec->delete();
        return $this->successResponse(null, 'EC supprimé.');
    }

    private const VOLUMES = ['volume_cm', 'volume_td', 'volume_tp', 'volume_td_tp'];

    /**
     * Un EC a un volume : par type (CM, TD, TP, réserve TP/TD), ou, pour un EC
     * pas encore ventilé, son total.
     */
    private function exigerUnVolume(array $valeurs, ?Ec $ec = null): void
    {
        $parType = array_sum(array_map(fn ($champ) => (int) ($valeurs[$champ] ?? $ec?->{$champ} ?? 0), self::VOLUMES));
        $total = (int) ($valeurs['volume_horaire'] ?? ($ec?->volume_a_ventiler ? $ec->volume_horaire : 0));

        if ($parType === 0 && $total === 0) {
            throw ValidationException::withMessages(['volume_cm' => 'Donnez au moins un volume : CM, TD, TP ou réserve TP/TD.']);
        }
    }

}
