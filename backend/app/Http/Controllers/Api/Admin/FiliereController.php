<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\Filiere;
use App\Models\Ue;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiliereController extends Controller
{
    use ScopedByEtablissement;

    public function index(Request $request): JsonResponse
    {
        $query = Filiere::withCount(['etudiants', 'ues']);

        // Scope par établissement pour les admins faculté
        $this->scopeQuery($query, $request);

        // Restriction à une année, pour les filtres en cascade. La définition de
        // « filière d'une année » vit sur Filiere::scopeForAnnee, que partage la
        // reconduction : deux définitions divergentes affichaient onze filières
        // pour une année dont la reconduction n'en reportait que dix.
        $anneeId = (int) $request->input('annee_id');

        if ($anneeId > 0) {
            $query->forAnnee($anneeId);
        }

        $filieres = $query->orderBy('intitule')->get();

        // Semestres réellement ouverts pour chaque filière cette année-là.
        //
        // Les écrans codaient jusqu'ici « 1 à 6 » en dur, alors que la colonne
        // ues.semestre va de 1 à 10 : aucun semestre de Master n'était donc
        // atteignable. On renvoie la liste réelle, en une seule requête groupée,
        // pour que le client n'ait pas à télécharger toutes les UEs.
        // Toujours renseignés, année choisie ou non : sans année, ce sont les
        // semestres de la filière toutes années confondues. Un contrat uniforme
        // évite au client d'avoir à distinguer « aucun semestre » de « champ
        // absent ».
        if ($filieres->isNotEmpty()) {
            $semestres = Ue::query()
                ->when($anneeId > 0, fn ($q) => $q->where('annee_id', $anneeId))
                ->whereIn('filiere_id', $filieres->pluck('id'))
                ->select('filiere_id', 'semestre')
                ->distinct()
                ->get()
                ->groupBy('filiere_id')
                ->map(fn ($lignes) => $lignes->pluck('semestre')->sort()->values()->all());

            $filieres->each(function ($filiere) use ($semestres) {
                $filiere->setAttribute('semestres', $semestres->get($filiere->id, []));
            });
        }

        return $this->successResponse($filieres);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'     => 'required|string|max:10|unique:filieres,code',
            'intitule' => 'required|string|max:255',
            'niveau'   => 'required|string|max:10',
        ]);

        // Assigner automatiquement l'établissement scope
        $validated['etablissement_id'] = $this->getEtablissementId($request);

        $filiere = Filiere::create($validated);

        // Lier automatiquement à l'année académique active
        $activeAnnee = AnneeAcademique::where('active', true)->first();
        if ($activeAnnee) {
            $filiere->anneesAcademiques()->syncWithoutDetaching([$activeAnnee->id]);
        }

        $filiere->loadCount(['etudiants', 'ues']);
        return $this->createdResponse($filiere, 'Filière créée avec succès.');
    }

    public function show(Request $request, Filiere $filiere): JsonResponse
    {
        $this->authorizeEtablissement($filiere, $request);

        $filiere->loadCount(['etudiants', 'ues']);
        $filiere->load('ues.ecs');
        return $this->successResponse($filiere);
    }

    public function update(Request $request, Filiere $filiere): JsonResponse
    {
        $this->authorizeEtablissement($filiere, $request);

        $validated = $request->validate([
            'code'     => 'sometimes|string|max:10|unique:filieres,code,' . $filiere->id,
            'intitule' => 'sometimes|string|max:255',
            'niveau'   => 'sometimes|string|max:10',
        ]);

        // Empêcher un admin faculté de changer l'établissement
        if ($this->getEtablissementId($request)) {
            unset($validated['etablissement_id']);
        }

        $filiere->update($validated);
        $filiere->loadCount(['etudiants', 'ues']);
        return $this->successResponse($filiere, 'Filière mise à jour.');
    }

    public function destroy(Request $request, Filiere $filiere): JsonResponse
    {
        $this->authorizeEtablissement($filiere, $request);
        $this->preventDeleteWithDependencies($filiere, [
            'etudiants'   => 'étudiant(s)',
            'ues'         => 'UE',
            'evenements'  => 'événement(s)',
        ]);

        $filiere->delete();
        return $this->successResponse(null, 'Filière supprimée.');
    }

    /**
     * Reconduire les filières d'une année source vers une année cible.
     */
    public function reconduire(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_annee_id' => 'required|exists:annees_academiques,id',
            'target_annee_id' => 'required|exists:annees_academiques,id|different:source_annee_id',
        ]);

        $sourceAnnee = AnneeAcademique::findOrFail($validated['source_annee_id']);
        $targetAnnee = AnneeAcademique::findOrFail($validated['target_annee_id']);

        // On reconduit ce que l'écran annonce. En lisant le seul pivot, ce bouton
        // abandonnait en silence les filières que l'import ou la promotion y
        // avaient omises, tout en affichant un décompte qui avait l'air d'une
        // confirmation.
        $filiereIds = Filiere::forAnnee($sourceAnnee->id)->pluck('id')->toArray();
        $targetAnnee->filieres()->syncWithoutDetaching($filiereIds);

        $count = count($filiereIds);
        return $this->successResponse([
            'reconduites' => $count,
            'source'       => $sourceAnnee->libelle,
            'target'       => $targetAnnee->libelle,
        ], "{$count} filière(s) reconduite(s) de {$sourceAnnee->libelle} vers {$targetAnnee->libelle}");
    }
}
