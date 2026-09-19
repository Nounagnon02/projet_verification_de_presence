<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FiliereResource;
use App\Models\AnneeAcademique;
use App\Models\Filiere;
use App\Models\Programme;
use App\Models\Ue;
use App\Services\SemesterService;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FiliereController extends Controller
{
    use ScopedByEtablissement;

    public function __construct(private readonly SemesterService $semestres = new SemesterService()) {}

    public function index(Request $request): JsonResponse
    {
        // Restriction à une année, pour les filtres en cascade. La définition de
        // « filière d'une année » vit sur Filiere::scopeForAnnee, que partage la
        // reconduction : deux définitions divergentes affichaient onze filières
        // pour une année dont la reconduction n'en reportait que dix.
        $anneeId = (int) $request->input('annee_id');
        $dansAnnee = function ($q) use ($anneeId) {
            if ($anneeId > 0) {
                $q->where('annee_id', $anneeId);
            }
        };

        // Effectifs de l'année choisie : sans année, l'écran additionnait toutes
        // les années. Les totaux, toutes années confondues, disent si la filière
        // peut être supprimée.
        $query = Filiere::with('programme:id,code,intitule')->withCount([
            'etudiants' => $dansAnnee,
            'ues'       => $dansAnnee,
            'etudiants as etudiants_total',
            'ues as ues_total',
            'evenements as evenements_total',
        ]);

        // Scope par établissement pour les admins faculté
        $this->scopeQuery($query, $request);

        if ($anneeId > 0) {
            $query->forAnnee($anneeId);
        }

        $filieres = $query->orderBy('intitule')->get();

        // Semestres réellement ouverts, et nombre d'UE de chacun. Les écrans
        // codaient « 1 à 6 » en dur, alors que ues.semestre va de 1 à 10 ; le
        // décompte par semestre fait apparaître une maquette anormale (deux
        // maquettes superposées dans la même année, par exemple). Une seule
        // requête groupée ; sans année, toutes années confondues.
        if ($filieres->isNotEmpty()) {
            $parSemestre = Ue::query()
                ->when($anneeId > 0, fn ($q) => $q->where('annee_id', $anneeId))
                ->whereIn('filiere_id', $filieres->pluck('id'))
                ->selectRaw('filiere_id, semestre, count(*) as n')
                ->groupBy('filiere_id', 'semestre')
                ->get()
                ->groupBy('filiere_id');

            $filieres->each(function (Filiere $filiere) use ($parSemestre) {
                $lignes = $parSemestre->get($filiere->id, collect())->sortBy('semestre');

                $filiere->setAttribute('semestres', $lignes->pluck('semestre')->map(fn ($s) => (int) $s)->values()->all());
                $filiere->setAttribute('ues_par_semestre', $lignes->mapWithKeys(fn ($l) => [(int) $l->semestre => (int) $l->n])->all());
            });
        }

        return $this->successResponse(FiliereResource::collection($filieres));
    }

    /**
     * Niveaux officiels et leurs semestres. Les écrans les lisent ici au lieu
     * de recopier « L1 à M2 » : une seule liste, que le serveur valide.
     *
     * GET /api/admin/niveaux
     */
    public function niveaux(): JsonResponse
    {
        return $this->successResponse(collect(SemesterService::MAPPING)->map(fn (array $semestres, string $code) => [
            'code'      => $code,
            'libelle'   => SemesterService::LIBELLES[$code] ?? $code,
            'semestres' => $semestres,
        ])->values());
    }

    /**
     * Crée une filière : un programme à un niveau.
     *
     * Le programme est désigné (programme_id), créé à la volée (programme_code,
     * programme_intitule), ou à défaut déduit du code de la filière. Code et
     * intitulé, s'ils manquent, en sont dérivés : IM + L2 donne IM-L2,
     * « Informatique et Mathématiques (L2) ». Le niveau n'est plus saisi trois
     * fois à la main.
     */
    public function store(Request $request): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);

        $validated = $request->validate([
            'programme_id'       => ['nullable', 'integer', $this->programmeDeLEtablissement($etablissementId)],
            'programme_code'     => ['nullable', 'string', 'max:15', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]*$/'],
            'programme_intitule' => ['nullable', 'string', 'max:255', 'required_with:programme_code'],
            'niveau'             => ['required', Rule::in($this->semestres->niveaux())],
            'code'               => ['nullable', 'string', 'max:20', 'required_without_all:programme_id,programme_code'],
            'intitule'           => ['nullable', 'string', 'max:255', 'required_without_all:programme_id,programme_code'],
        ], $this->messages());

        $niveau = $validated['niveau'];
        $programme = $this->programme($validated, $etablissementId, $niveau);

        $code = trim((string) ($validated['code'] ?? '')) ?: "{$programme->code}-{$niveau}";
        $intitule = trim((string) ($validated['intitule'] ?? '')) ?: "{$programme->intitule} ({$niveau})";

        if (mb_strlen($code) > 20) {
            throw ValidationException::withMessages(['code' => "Le code {$code} dépasse 20 caractères : raccourcissez celui du programme."]);
        }

        $this->verifierCodeLibre($code, $etablissementId);

        $filiere = Filiere::create([
            'code'             => $code,
            'intitule'         => $intitule,
            'niveau'           => $niveau,
            'programme_id'     => $programme->id,
            'etablissement_id' => $etablissementId,
        ]);

        // Rattachée à l'année active DE SON ÉTABLISSEMENT : on prenait la
        // première année active venue, fût-elle celle d'une autre faculté.
        $activeAnnee = AnneeAcademique::activePour($etablissementId);

        if ($activeAnnee) {
            $filiere->anneesAcademiques()->syncWithoutDetaching([$activeAnnee->id]);
        }

        $filiere->load('programme:id,code,intitule')->loadCount(['etudiants', 'ues']);

        return $this->createdResponse(new FiliereResource($filiere), 'Filière créée avec succès.');
    }

    public function show(Request $request, Filiere $filiere): JsonResponse
    {
        $this->authorizeEtablissement($filiere, $request);

        $filiere->loadCount(['etudiants', 'ues']);
        $filiere->load(['programme:id,code,intitule', 'ues.ecs']);
        return $this->successResponse(new FiliereResource($filiere));
    }

    public function update(Request $request, Filiere $filiere): JsonResponse
    {
        $this->authorizeEtablissement($filiere, $request);

        $validated = $request->validate([
            'programme_id' => ['sometimes', 'nullable', 'integer', $this->programmeDeLEtablissement($this->getEtablissementId($request))],
            'code'         => ['sometimes', 'string', 'max:20'],
            'intitule'     => ['sometimes', 'string', 'max:255'],
            'niveau'       => ['sometimes', Rule::in($this->semestres->niveaux())],
        ], $this->messages());

        if (isset($validated['code'])) {
            $this->verifierCodeLibre($validated['code'], $filiere->etablissement_id, $filiere->id);
        }

        // Le niveau porte les semestres des UE : changer celui d'une filière qui
        // a déjà des UE les rendrait toutes incohérentes, sans que rien ne le
        // signale ensuite.
        if (isset($validated['niveau']) && $validated['niveau'] !== $filiere->niveau) {
            $semestres = $filiere->ues()->distinct()->orderBy('semestre')->pluck('semestre');

            if ($semestres->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'niveau' => "Le niveau de {$filiere->code} ne peut plus changer : ses UE sont en "
                        . $semestres->map(fn ($s) => "S{$s}")->implode(', ')
                        . ", propres au niveau {$filiere->niveau}. Pour faire passer ses étudiants au niveau suivant, utilisez la promotion.",
                ]);
            }
        }

        $filiere->update($validated);
        $filiere->load('programme:id,code,intitule')->loadCount(['etudiants', 'ues']);

        return $this->successResponse(new FiliereResource($filiere), 'Filière mise à jour.');
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
        $this->refuserSiAnneeClose($targetAnnee, $request);

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

    /** Programme désigné, créé, ou déduit du code de la filière. */
    private function programme(array $v, ?int $etablissementId, string $niveau): Programme
    {
        if (!empty($v['programme_id'])) {
            return Programme::findOrFail($v['programme_id']);
        }

        [$code, $intitule] = !empty($v['programme_code'])
            ? [mb_strtoupper(trim($v['programme_code'])), trim((string) $v['programme_intitule'])]
            : Programme::deduire(trim((string) $v['code']), trim((string) $v['intitule']), $niveau);

        return Programme::firstOrCreate(
            ['etablissement_id' => $etablissementId, 'code' => $code],
            ['intitule' => $intitule !== '' ? $intitule : $code],
        );
    }

    /** Un programme existant, et de l'établissement de l'admin. */
    private function programmeDeLEtablissement(?int $etablissementId): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('programmes', 'id')->where(function ($q) use ($etablissementId) {
            if ($etablissementId) {
                $q->where('etablissement_id', $etablissementId);
            }
        });
    }

    /**
     * Le code d'une filière n'est unique que dans son établissement : deux
     * facultés peuvent chacune avoir leur IM-L1. Casse ignorée.
     */
    private function verifierCodeLibre(string $code, ?int $etablissementId, ?int $sauf = null): void
    {
        $pris = Filiere::where('etablissement_id', $etablissementId)
            ->whereRaw('lower(code) = ?', [mb_strtolower(trim($code))])
            ->when($sauf, fn ($q) => $q->where('id', '!=', $sauf))
            ->exists();

        if ($pris) {
            throw ValidationException::withMessages(['code' => "Le code {$code} existe déjà dans votre établissement."]);
        }
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'niveau.in' => 'Niveau inconnu : choisissez parmi ' . implode(', ', $this->semestres->niveaux()) . '.',
            'programme_code.regex' => 'Le code du programme ne contient que des lettres, des chiffres et des tirets.',
            'code.required_without_all' => 'Choisissez un programme, ou donnez le code de la filière.',
            'intitule.required_without_all' => 'Choisissez un programme, ou donnez l\'intitulé de la filière.',
        ];
    }
}
