<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\EtudiantResource;
use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    /**
     * Liste paginée des étudiants avec filtres.
     * GET /api/admin/students?per_page=15&search=&filiere_id=&annee_id=
     */
    public function index(): AnonymousResourceCollection
    {
        $query = Etudiant::with(['filiere', 'anneeAcademique']);

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

        $perPage = min((int) request('per_page', 15), 100);

        return EtudiantResource::collection(
            $query->orderBy('created_at', 'desc')->paginate($perPage)
        );
    }

    /**
     * Détail d'un étudiant.
     * GET /api/admin/students/{student}
     */
    public function show(Etudiant $student): EtudiantResource
    {
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
        $filiere = Filiere::findOrFail($request->filiere_id);
        $annee   = AnneeAcademique::findOrFail($request->annee_id);

        $identifiantUnique = $this->generateDeterministicId(
            $request->nom,
            $request->prenom,
            $request->matricule,
            $filiere->code,
            $annee->libelle
        );

        $etudiant = Etudiant::create([
            'id'                => (string) Str::uuid(),
            'nom'               => mb_strtoupper($this->removeAccents($request->nom)),
            'prenom'            => mb_strtoupper($this->removeAccents($request->prenom)),
            'matricule'         => $request->matricule,
            'filiere_id'        => $request->filiere_id,
            'annee_id'          => $request->annee_id,
            'email'             => $request->email,
            'identifiant_unique' => $identifiantUnique,
        ]);

        dispatch(function () use ($etudiant) {
            try {
                Mail::to($etudiant->email)->send(new \App\Mail\StudentRegisteredMail($etudiant));
            } catch (\Exception $e) {
                \Log::error("Email d'inscription échoué pour {$etudiant->email}: " . $e->getMessage());
            }
        });

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
        $data = $request->validated();

        if ($request->filled('nom') || $request->filled('prenom')) {
            $nom     = $request->filled('nom') ? $request->nom : $student->nom;
            $prenom  = $request->filled('prenom') ? $request->prenom : $student->prenom;
            $filiere = Filiere::findOrFail($data['filiere_id'] ?? $student->filiere_id);
            $annee   = AnneeAcademique::findOrFail($data['annee_id'] ?? $student->annee_id);

            $data['identifiant_unique'] = $this->generateDeterministicId(
                $nom, $prenom,
                $data['matricule'] ?? $student->matricule,
                $filiere->code,
                $annee->libelle
            );
        }

        if (isset($data['nom'])) {
            $data['nom'] = mb_strtoupper($this->removeAccents($data['nom']));
        }
        if (isset($data['prenom'])) {
            $data['prenom'] = mb_strtoupper($this->removeAccents($data['prenom']));
        }

        $student->update($data);

        return $this->successResponse(
            new EtudiantResource($student->load(['filiere', 'anneeAcademique'])),
            'Étudiant mis à jour avec succès.'
        );
    }

    /**
     * Suppression d'un étudiant.
     * DELETE /api/admin/students/{student}
     */
    public function destroy(Etudiant $student): JsonResponse
    {
        $student->delete();

        return $this->successResponse(null, 'Étudiant supprimé avec succès.');
    }

    private function generateDeterministicId(
        string $nom,
        string $prenom,
        string $matricule,
        string $filiereCode,
        string $anneeLibelle
    ): string {
        return implode('_', [
            $this->sanitize($nom),
            $this->sanitize($prenom),
            $this->sanitize($matricule),
            $this->sanitize($filiereCode),
            $this->sanitize($anneeLibelle),
        ]);
    }

    private function sanitize(string $value): string
    {
        return mb_strtoupper(str_replace([' ', '-'], '_', $this->removeAccents($value)));
    }

    private function removeAccents(string $value): string
    {
        return strtr(utf8_decode($value),
            utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'),
            'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
    }
}
