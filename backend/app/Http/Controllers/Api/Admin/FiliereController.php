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

        // Restriction à une année académique, pour les filtres en cascade.
        //
        // On NE se fie PAS au seul pivot « filiere_annee ». Ce pivot n'est
        // alimenté qu'à la création d'une filière (méthode store, année active) :
        // ni l'import d'étudiants, ni StudentPromotionService ne le mettent à
        // jour. Après une promotion, il désigne encore l'année précédente, si
        // bien qu'une cascade fondée sur lui seul masquerait des filières
        // pourtant peuplées — le cas réel de DEMO-IM-L1, qui compte un étudiant
        // en 2025-2026 sans ligne de pivot.
        //
        // La règle retenue : une filière appartient à une année si elle y a du
        // contenu (étudiants, UEs ou événements) OU si le pivot le déclare. Le
        // pivot reste utile pour la filière fraîchement créée, encore vide, que
        // l'on doit pouvoir choisir afin d'y inscrire le premier étudiant.
        $anneeId = (int) $request->input('annee_id');

        if ($anneeId > 0) {
            $query->where(function ($q) use ($anneeId) {
                $q->whereHas('anneesAcademiques', fn ($p) => $p->where('annee_id', $anneeId))
                  ->orWhereHas('etudiants', fn ($p) => $p->where('annee_id', $anneeId))
                  ->orWhereHas('ues', fn ($p) => $p->where('annee_id', $anneeId))
                  ->orWhereHas('evenements', fn ($p) => $p->where('annee_id', $anneeId));
            });
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

        $filiereIds = $sourceAnnee->filieres()->pluck('filieres.id')->toArray();
        $targetAnnee->filieres()->syncWithoutDetaching($filiereIds);

        $count = count($filiereIds);
        return $this->successResponse([
            'reconduites' => $count,
            'source'       => $sourceAnnee->libelle,
            'target'       => $targetAnnee->libelle,
        ], "{$count} filière(s) reconduite(s) de {$sourceAnnee->libelle} vers {$targetAnnee->libelle}");
    }
}
