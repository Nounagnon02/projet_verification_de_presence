<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\EtudiantResource;
use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Services\IdentifiantService;
use App\Services\StudentPromotionService;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    use ScopedByEtablissement;

    /**
     * Liste paginée des étudiants avec filtres.
     * GET /api/admin/students?per_page=15&search=&filiere_id=&annee_id=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Etudiant::with(['filiere', 'anneeAcademique', 'groupes:groupes.id,groupes.libelle,groupes.type']);

        // Scope par établissement via la filière
        $this->scopeViaRelation($query, $request, 'filiere');

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('matricule', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($filiereId = request('filiere_id')) {
            $query->where('filiere_id', $filiereId);
        }

        if ($anneeId = request('annee_id')) {
            $query->where('annee_id', $anneeId);
        }

        if ($niveau = request('niveau')) {
            $query->whereHas('filiere', fn($q) => $q->where('niveau', $niveau));
        }

        if ($semestre = request('semestre')) {
            $query->whereHas('ecs.ue', fn($q) => $q->where('semestre', $semestre));
        }

        // Filtre délégué. Testé sur la présence du paramètre et non sur sa
        // valeur : `responsable=0` doit pouvoir ne montrer que les non-délégués,
        // ce qu'un `if ($x = request(...))` avalerait comme un filtre absent.
        if ($request->filled('responsable')) {
            $query->where('est_responsable', $request->boolean('responsable'));
        }

        if ($request->filled('groupe_id')) {
            $query->whereHas('groupes', fn ($g) => $g->where('groupes.id', $request->integer('groupe_id')));
        }

        $perPage = min((int) request('per_page', 15), 100);
        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Liste des étudiants récupérée.',
            'data'    => EtudiantResource::collection($paginator->items()),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * Détail d'un étudiant.
     * GET /api/admin/students/{student}
     */
    public function show(Request $request, Etudiant $student): EtudiantResource
    {
        $this->authorizeEtablissement($student, $request, 'filiere');

        $student->load(['filiere', 'anneeAcademique', 'presences.evenement.ec']);

        return new EtudiantResource($student);
    }

    /**
     * Inscription individuelle (US01).
     * Conforme CDC 7.1.1 & 7.1.3.
     * POST /api/admin/students
     */
    public function store(StoreStudentRequest $request): JsonResponse
    {
        $matricule = $request->matricule;

        $filiere = Filiere::findOrFail($request->filiere_id);

        // L'inscription se fait toujours dans l'année active : on l'impose ici
        // plutôt que de laisser l'administrateur la choisir. C'est celle de
        // l'établissement de la filière — les facultés ne basculent pas toutes
        // le même jour.
        $annee = AnneeAcademique::activePour($filiere->etablissement_id);

        if (!$annee) {
            return $this->errorResponse(
                "Aucune année académique active n'est définie. Activez une année avant d'inscrire des étudiants.",
                422
            );
        }

        $identifiantUnique = IdentifiantService::generate(
            $request->nom,
            $request->prenom,
            $matricule,
            $filiere->id,
            $annee->id
        );

        // L'insertion et l'auto-inscription aux ECs (CDC 7.2.3) forment un tout :
        // sans transaction, une erreur pendant l'auto-inscription laissait un
        // étudiant créé mais sans cours, avec un 500 côté client — l'inscription
        // paraissait à la fois faite et échouée.
        // L'unicite validee plus haut ne porte que sur les etudiants vivants. Une
        // ligne supprimee peut donc encore detenir l'email ou l'identifiant
        // demande, sans que le matricule permette de la retrouver. Sans ce
        // filet, l'index unique renvoyait une exception PDO brute en 500.
        try {
            $etudiant = DB::transaction(function () use ($request, $matricule, $filiere, $annee, $identifiantUnique) {
                $champs = [
                    'nom'               => IdentifiantService::normalize($request->nom),
                    'prenom'            => IdentifiantService::normalize($request->prenom),
                    'matricule'         => $matricule,
                    'filiere_id'        => $filiere->id,
                    'annee_id'          => $annee->id,
                    'email'             => $request->email,
                    'identifiant_unique' => $identifiantUnique,
                ];

                // Reinscription d'un etudiant supprime.
                //
                // La suppression d'un etudiant est douce, et elle doit l'etre : les
                // presences portent etudiant_id, une suppression franche effacerait
                // l'historique de presence. Mais l'index unique du matricule, lui,
                // compte les lignes supprimees. Sans ce chemin, reinscrire un
                // etudiant supprime etait refuse par une ligne invisible et
                // irrecuperable — aucune route ne l'expose. On restaure donc, ce qui
                // rend au passage son historique a l'etudiant.
                $ancien = Etudiant::onlyTrashed()->where('matricule', $matricule)->first();

                if ($ancien) {
                    $ancien->restore();
                    $ancien->update($champs);
                    $ancien->recalculateEnrollments();

                    return $ancien;
                }

                $etudiant = Etudiant::create(['id' => (string) Str::uuid()] + $champs);

                $etudiant->autoEnroll();

                return $etudiant;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return $this->errorResponse(
                "Cet email ou cet identifiant appartient encore a un etudiant supprime. "
                . "Reinscrivez-le avec son matricule d'origine pour recuperer son dossier.",
                409
            );
        }

        // La filière et l'année sont déjà chargées : les rattacher évite les deux
        // requêtes du load() final. Au-delà des ~400 ms gagnés en production,
        // cela supprime la dernière opération capable d'échouer alors que
        // l'inscription est déjà validée en base.
        $etudiant->setRelation('filiere', $filiere);
        $etudiant->setRelation('anneeAcademique', $annee);

        $this->envoyerIdentifiantParEmail($etudiant);

        return $this->createdResponse(
            new EtudiantResource($etudiant),
            'Étudiant inscrit avec succès.'
        );
    }

    /**
     * Envoi de l'identifiant unique par email (synchrone).
     *
     * L'inscription est déjà validée en base quand cette méthode est appelée :
     * aucune défaillance d'envoi ne doit la transformer en erreur côté client.
     */
    private function envoyerIdentifiantParEmail(Etudiant $etudiant): void
    {
        try {
            Mail::send('emails.identifiant', [
                'nom' => $etudiant->nom,
                'prenom' => $etudiant->prenom,
                'identifiant' => $etudiant->identifiant_unique,
                'filiere' => $etudiant->filiere->intitule,
                'annee' => $etudiant->anneeAcademique->libelle,
            ], function ($message) use ($etudiant) {
                $message->to($etudiant->email)
                    ->subject('Votre identifiant unique - Système de présence UAC');
            });
        } catch (\Throwable $e) {
            // \Throwable et non \Exception : une erreur de configuration du mailer
            // (classe de transport absente, driver inconnu) lève une \Error qui
            // ferait échouer l'inscription entière avec un 500.
            try {
                Log::error("Erreur envoi email étudiant {$etudiant->matricule}: " . $e->getMessage());
            } catch (\Throwable) {
                // Le journal lui-même peut être indisponible (canal stderr non
                // ouvrable sous Apache) : sans ce second filet, c'est le
                // traitement de l'erreur d'e-mail qui provoquait le 500.
            }
        }
    }

    /**
     * Mise à jour d'un étudiant.
     * PUT/PATCH /api/admin/students/{student}
     */
    public function update(UpdateStudentRequest $request, Etudiant $student): JsonResponse
    {
        $this->authorizeEtablissement($student, $request, 'filiere');

        $data = $request->validated();

        if ($request->filled('nom') || $request->filled('prenom')) {
            $nom     = $request->filled('nom') ? $request->nom : $student->nom;
            $prenom  = $request->filled('prenom') ? $request->prenom : $student->prenom;

            $data['identifiant_unique'] = IdentifiantService::generate(
                $nom, $prenom,
                $data['matricule'] ?? $student->matricule,
                $data['filiere_id'] ?? $student->filiere_id,
                $data['annee_id'] ?? $student->annee_id
            );
        }

        if (isset($data['nom'])) {
            $data['nom'] = IdentifiantService::normalize($data['nom']);
        }
        if (isset($data['prenom'])) {
            $data['prenom'] = IdentifiantService::normalize($data['prenom']);
        }

        if (isset($data['annee_id']) && (int) $data['annee_id'] !== (int) $student->annee_id) {
            $this->refuserSiAnneeClose((int) $data['annee_id'], $request);
        }

        $oldFiliereId = $student->filiere_id;
        $oldAnneeId   = $student->annee_id;

        $student->update($data);

        // Si la filière ou l'année a changé, recalculer les inscriptions aux ECs.
        // Changer l'année ici corrige une erreur de saisie (le passage d'année
        // se fait par la promotion) : les inscriptions de l'année erronée sont
        // effacées avec celles de l'année courante.
        if ($student->filiere_id !== $oldFiliereId || $student->annee_id !== $oldAnneeId) {
            $student->recalculateEnrollments($student->annee_id !== $oldAnneeId ? $oldAnneeId : null);
        }

        return $this->successResponse(
            new EtudiantResource($student->load(['filiere', 'anneeAcademique'])),
            'Étudiant mis à jour avec succès.'
        );
    }

    /**
     * Suppression d'un étudiant.
     * DELETE /api/admin/students/{student}
     */
    public function destroy(Request $request, Etudiant $student): JsonResponse
    {
        $this->authorizeEtablissement($student, $request, 'filiere');

        $student->delete();

        return $this->successResponse(null, 'Étudiant supprimé avec succès.');
    }

    /**
     * Promeut en masse les étudiants d'une filière vers une autre (ex.
     * IM-L1 → IM-L2), avec recalcul des inscriptions aux ECs. Le passage
     * d'une année à la suivante se fait ici, et non dans l'édition d'un
     * étudiant : c'est une opération de promotion, pas une correction.
     *
     * POST /api/admin/students/promote
     * Body : from_filiere_id, to_filiere_id, [to_annee_id], [dry_run]
     */
    public function promote(Request $request, StudentPromotionService $service): JsonResponse
    {
        // to_filiere_id n'est requis que pour exécuter la promotion : en mode
        // prévisualisation, on ne compte que les étudiants de la filière source.
        $validated = $request->validate([
            'from_filiere_id' => ['required', 'integer', 'exists:filieres,id'],
            'to_filiere_id'   => ['required_unless:dry_run,true', 'integer', 'exists:filieres,id', 'different:from_filiere_id'],
            'to_annee_id'     => ['nullable', 'integer', 'exists:annees_academiques,id'],
            'dry_run'         => ['sometimes', 'boolean'],
        ], [
            'to_filiere_id.different' => 'La filière de départ et la filière de destination doivent être différentes.',
        ]);

        $from = Filiere::findOrFail($validated['from_filiere_id']);
        $this->authorizeEtablissement($from, $request);

        $concernes = $service->countEligible($from);

        // Mode prévisualisation : renvoie le nombre d'étudiants concernés
        // sans rien modifier, pour confirmation côté interface.
        if ($request->boolean('dry_run')) {
            return $this->successResponse(
                ['etudiants_concernes' => $concernes],
                "{$concernes} étudiant(s) dans la filière {$from->code}."
            );
        }

        $to = Filiere::findOrFail($validated['to_filiere_id']);
        $this->authorizeEtablissement($to, $request);

        $toAnnee = null;
        if (!empty($validated['to_annee_id'])) {
            // Les années sont communes à l'université : pas d'établissement à vérifier.
            $toAnnee = AnneeAcademique::findOrFail($validated['to_annee_id']);
        }

        if ($concernes === 0) {
            return $this->errorResponse("Aucun étudiant à promouvoir dans la filière {$from->code}.", 422);
        }

        $promus = $service->promote($from, $to, $toAnnee);

        return $this->successResponse(
            ['etudiants_promus' => $promus],
            "{$promus} étudiant(s) promu(s) de {$from->code} vers {$to->code}."
        );
    }

}
