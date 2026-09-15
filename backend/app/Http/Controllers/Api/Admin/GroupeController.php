<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ec;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\Groupe;
use App\Services\Groupes\GestionGroupes;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Groupes de TD et de TP d'une promotion : les lister, les créer, répartir les
 * étudiants, affecter un étudiant.
 */
class GroupeController extends Controller
{
    use ScopedByEtablissement;

    public function __construct(private readonly GestionGroupes $groupes) {}

    /**
     * GET /api/admin/groupes?filiere_id=&annee_id=&type=
     * GET /api/admin/groupes?ec_id=&type=   — groupes proposés pour une séance de cet EC
     */
    public function index(Request $request): JsonResponse
    {
        $query = Groupe::with('filiere:id,code')->withCount('etudiants')->orderBy('type')->orderBy('libelle');
        $this->scopeViaRelation($query, $request, 'filiere');

        if ($request->filled('ec_id')) {
            // Les groupes des filières qui suivent le cours, pour son année.
            $ue = Ec::with('ue')->findOrFail($request->integer('ec_id'))->ue;
            $query->where('annee_id', $ue->annee_id)->whereIn('filiere_id', $ue->filieres()->pluck('filieres.id'));
        }

        foreach (['filiere_id', 'annee_id', 'type'] as $champ) {
            if ($request->filled($champ)) {
                $query->where($champ, $request->input($champ));
            }
        }

        return $this->successResponse($query->get());
    }

    /** POST /api/admin/groupes */
    public function store(Request $request): JsonResponse
    {
        $valeurs = $request->validate([
            'filiere_id' => 'required|integer|exists:filieres,id',
            'annee_id'   => 'required|integer|exists:annees_academiques,id',
            'type'       => ['required', Rule::in(Groupe::TYPES)],
            'libelle'    => 'required|string|max:30',
        ]);
        $valeurs['libelle'] = mb_strtoupper(trim($valeurs['libelle']));

        $this->authorizeEtablissement(Filiere::findOrFail($valeurs['filiere_id']), $request);
        $this->refuserSiAnneeClose((int) $valeurs['annee_id'], $request);

        if (Groupe::where($valeurs)->exists()) {
            return $this->errorResponse("Le groupe {$valeurs['libelle']} existe déjà dans cette promotion.", 422);
        }

        return $this->createdResponse(Groupe::create($valeurs)->loadCount('etudiants'), "Groupe {$valeurs['libelle']} créé.");
    }

    /**
     * Un groupe qui porte des séances ou des créneaux ne se supprime pas : ils
     * deviendraient, sans que rien ne le dise, des séances de toute la promotion.
     *
     * DELETE /api/admin/groupes/{groupe}
     */
    public function destroy(Request $request, Groupe $groupe): JsonResponse
    {
        $this->authorizeEtablissement($groupe, $request, 'filiere');
        $this->refuserSiAnneeClose((int) $groupe->annee_id, $request);

        if ($groupe->evenements()->exists() || $groupe->creneaux()->exists()) {
            return $this->errorResponse("Le groupe {$groupe->libelle} a des séances ou des créneaux d'emploi du temps : réaffectez-les d'abord.", 409);
        }

        $groupe->delete();

        return $this->successResponse(null, "Groupe {$groupe->libelle} supprimé.");
    }

    /**
     * Répartit la promotion en N groupes de même taille, par ordre de matricule.
     *
     * POST /api/admin/groupes/repartir
     */
    public function repartir(Request $request): JsonResponse
    {
        $valeurs = $request->validate([
            'filiere_id' => 'required|integer|exists:filieres,id',
            'annee_id'   => 'required|integer|exists:annees_academiques,id',
            'type'       => ['required', Rule::in(Groupe::TYPES)],
            'nombre'     => 'required|integer|min:1|max:30',
        ]);

        $filiere = Filiere::findOrFail($valeurs['filiere_id']);
        $this->authorizeEtablissement($filiere, $request);
        $this->refuserSiAnneeClose((int) $valeurs['annee_id'], $request);

        $effectifs = $this->groupes->repartir($filiere, (int) $valeurs['annee_id'], $valeurs['type'], (int) $valeurs['nombre']);
        $detail = implode(', ', array_map(fn ($libelle, $n) => "{$libelle} ({$n})", array_keys($effectifs), $effectifs));

        return $this->successResponse(
            ['effectifs' => $effectifs],
            array_sum($effectifs) . " étudiant(s) de {$filiere->code} répartis en " . count($effectifs) . ' groupe(s) de ' . strtoupper($valeurs['type']) . " : {$detail}."
        );
    }

    /**
     * Groupe de TD et groupe de TP d'un étudiant, pour son année ; null le retire.
     *
     * PUT /api/admin/students/{student}/groupes
     */
    public function affecter(Request $request, Etudiant $student): JsonResponse
    {
        $this->authorizeEtablissement($student, $request, 'filiere');
        $this->refuserSiAnneeClose((int) $student->annee_id, $request);

        $valeurs = $request->validate([
            'td' => 'sometimes|nullable|integer|exists:groupes,id',
            'tp' => 'sometimes|nullable|integer|exists:groupes,id',
        ]);

        foreach ($valeurs as $type => $groupeId) {
            if ($groupeId === null) {
                $this->groupes->retirer($student, $type, (int) $student->annee_id);
                continue;
            }

            $groupe = Groupe::findOrFail($groupeId);

            if ($groupe->type !== $type) {
                return $this->errorResponse("{$groupe->libelle} est un groupe de " . strtoupper($groupe->type) . '.', 422);
            }

            $this->groupes->affecter($student, $groupe);
        }

        $groupes = $student->groupes()->wherePivot('annee_id', $student->annee_id)->get(['groupes.id', 'groupes.libelle', 'groupes.type']);

        return $this->successResponse(
            $groupes->map(fn ($g) => ['id' => $g->id, 'libelle' => $g->libelle, 'type' => $g->type])->values(),
            "Groupes de {$student->prenom} {$student->nom} mis à jour."
        );
    }
}
