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
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $query = Etudiant::with(['filiere', 'anneeAcademique']);

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
        // plutôt que de laisser l'administrateur la choisir. L'année est celle
        // de l'établissement de la filière (une seule active à la fois).
        $annee = AnneeAcademique::where('active', true)
            ->when($filiere->etablissement_id, fn ($q) => $q->where('etablissement_id', $filiere->etablissement_id))
            ->first();

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

        $etudiant = Etudiant::create([
            'id'                => (string) Str::uuid(),
            'nom'               => IdentifiantService::normalize($request->nom),
            'prenom'            => IdentifiantService::normalize($request->prenom),
            'matricule'         => $matricule,
            'filiere_id'        => $filiere->id,
            'annee_id'          => $annee->id,
            'email'             => $request->email,
            'identifiant_unique' => $identifiantUnique,
        ]);

        // Auto-inscription aux ECs de la filière et année (CDC 7.2.3)
        $etudiant->autoEnroll();

        // Envoi de l'identifiant par email (synchrone — pas de worker en prod)
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
            \Illuminate\Support\Facades\Log::error("Erreur envoi email étudiant {$etudiant->matricule}: " . $e->getMessage());
        }

        return $this->createdResponse(
            new EtudiantResource($etudiant->load(['filiere', 'anneeAcademique'])),
            'Étudiant inscrit avec succès.'
        );
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

        $oldFiliereId = $student->filiere_id;
        $oldAnneeId   = $student->annee_id;

        $student->update($data);

        // Si la filière ou l'année a changé, recalculer les inscriptions aux ECs
        if ($student->filiere_id !== $oldFiliereId || $student->annee_id !== $oldAnneeId) {
            $student->recalculateEnrollments();
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

}
