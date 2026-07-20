<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessGeminiImportJob;
use App\Models\Analyse;
use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Services\GeminiService;
use App\Services\IdentifiantService;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    use ScopedByEtablissement;

    public function __construct(
        protected GeminiService $gemini
    ) {}

    /**
     * Importation des étudiants via CSV (US02).
     * Conforme CDC 7.2.
     *
     * POST /api/admin/import/students
     */
    public function students(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $file   = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle, 1000, ',');

        if (!$header || count($header) < 3) {
            fclose($handle);
            return $this->errorResponse('Le fichier CSV est invalide ou vide.', 422);
        }

        $header = array_map(fn ($h) => trim(mb_strtolower($h)), $header);

        // Vérifier que le CSV contient au moins une ligne de données
        $firstRow = fgetcsv($handle, 1000, ',');
        if ($firstRow === false || count($firstRow) < 2) {
            fclose($handle);
            return $this->errorResponse('Le fichier CSV ne contient aucune donnée.', 422);
        }

        // Réinjecter la première ligne dans le buffer pour le traitement
        // On utilise un fichier temporaire pour rejouer tout le contenu
        fclose($handle);
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle, 1000, ','); // relire le header
        $header = array_map(fn ($h) => trim(mb_strtolower($h)), $header);

        // Mapping des en-têtes CDC vers les champs internes
        $columnMap = [
            'nom' => 'nom',
            'prenom' => 'prenom',
            'matricule' => 'matricule',
            'filiere' => 'filiere_code',
            'annee' => 'annee_libelle',
            'email' => 'email',
            'telephone' => 'telephone',
        ];

        $mapped = [];
        foreach ($header as $col) {
            if (isset($columnMap[$col])) {
                $mapped[] = $columnMap[$col];
            } else {
                $mapped[] = $col;
            }
        }
        $header = $mapped;

        $results = ['success' => 0, 'errors' => [], 'total' => 0];

        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            $results['total']++;
            $data = array_combine($header, $row);

            $rowValidator = Validator::make($data, [
                'nom'           => ['required', 'string', 'max:100'],
                'prenom'        => ['required', 'string', 'max:100'],
                'matricule'     => ['required', 'string', 'unique:etudiants,matricule'],
                'filiere_code'  => ['required', 'string', 'exists:filieres,code'],
                'annee_libelle' => ['required', 'string', 'exists:annees_academiques,libelle'],
                'email'         => ['required', 'email', 'unique:etudiants,email'],
            ]);

            if ($rowValidator->fails()) {
                $results['errors'][] = [
                    'row'    => $data['matricule'] ?? 'N/A',
                    'errors' => $rowValidator->errors()->all(),
                ];
                continue;
            }

            $filiere = Filiere::where('code', $data['filiere_code'])->first();
            $annee   = AnneeAcademique::where('libelle', $data['annee_libelle'])->first();

            $etudiant = Etudiant::create([
                'id'                 => (string) Str::uuid(),
                'nom'                => IdentifiantService::normalize($data['nom']),
                'prenom'             => IdentifiantService::normalize($data['prenom']),
                'matricule'          => $data['matricule'],
                'filiere_id'         => $filiere->id,
                'annee_id'           => $annee->id,
                'email'              => $data['email'],
                'identifiant_unique' => IdentifiantService::generate(
                    $data['nom'], $data['prenom'], $data['matricule'],
                    $filiere->id, $annee->id
                ),
            ]);

            // Auto-inscription aux ECs de la filière et année (CDC 7.2.3)
            $etudiant->autoEnroll();

            // Envoi de l'identifiant par email via queue (job asynchrone) — CDC 7.1.2
            \App\Jobs\SendIdentifiantEmailJob::dispatch($etudiant);

            $results['success']++;
        }

        fclose($handle);

        return $this->successResponse(
            $results,
            "Importation terminée : {$results['success']}/{$results['total']} étudiant(s) importé(s)."
        );
    }

    /**
     * Importation et analyse des cours (UEs/ECs) via PDF (Gemini IA) — ASYNCHRONE.
     * Conforme CDC 8.1 & 8.4 — l'analyse est déléguée à un job de queue.
     *
     * POST /api/admin/import/courses
     */
    public function courses(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'file' => 'required|file|mimes:pdf|mimetypes:application/pdf|max:20480',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $file = $request->file('file');

        // Vérification des magic bytes PDF (%PDF en début de fichier)
        $handle = fopen($file->getRealPath(), 'r');
        $magic = fread($handle, 4);
        fclose($handle);

        if ($magic !== '%PDF') {
            return $this->errorResponse('Le fichier fourni n\'est pas un PDF valide.', 422);
        }

        $path    = $file->store('imports/courses', 'supabase');

        // Création de l'analyse en base (statut: pending)
        // On stocke le chemin RELATIF — le job utilise Storage::path() pour le résoudre
        $analyse = Analyse::create([
            'type'      => 'courses',
            'status'    => 'pending',
            'file_path' => $path,
            'user_id'   => Auth::id(),
        ]);

        // Dispatch du job asynchrone sur queue dédiée
        ProcessGeminiImportJob::dispatch($analyse, 'courses')
            ->onQueue('gemini-import');

        return $this->successResponse(
            ['analysis_id' => $analyse->id, 'status' => 'pending'],
            'Analyse des cours lancée en arrière-plan.'
        );
    }

    /**
     * Importation et analyse de l'emploi du temps via PDF (US03 - Gemini IA) — ASYNCHRONE.
     * Conforme CDC 8.1 & 8.2 — l'analyse est déléguée à un job de queue.
     *
     * POST /api/admin/import/schedule
     */
    public function schedule(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'file' => 'required|file|mimes:pdf|mimetypes:application/pdf|max:20480',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $file = $request->file('file');

        // Vérification des magic bytes PDF (%PDF en début de fichier)
        $handle = fopen($file->getRealPath(), 'r');
        $magic = fread($handle, 4);
        fclose($handle);

        if ($magic !== '%PDF') {
            return $this->errorResponse('Le fichier fourni n\'est pas un PDF valide.', 422);
        }

        $path    = $file->store('imports/schedule', 'supabase');

        // Création de l'analyse en base (statut: pending)
        // On stocke le chemin RELATIF — le job utilise Storage::path() pour le résoudre
        $analyse = Analyse::create([
            'type'      => 'schedule',
            'status'    => 'pending',
            'file_path' => $path,
            'user_id'   => Auth::id(),
        ]);

        // Dispatch du job asynchrone sur queue dédiée
        ProcessGeminiImportJob::dispatch($analyse, 'schedule')
            ->onQueue('gemini-import');

        return $this->successResponse(
            ['analysis_id' => $analyse->id, 'status' => 'pending'],
            'Analyse de l\'emploi du temps lancée en arrière-plan.'
        );
    }

    /**
     * Validation et sauvegarde en masse des événements extraits.
     *
     * POST /api/admin/import/validate-events
     */
    public function validateEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'events'            => 'required|array|min:1',
            'events.*.ec_id'    => 'required|exists:ecs,id',
            'events.*.filiere_id' => 'required|exists:filieres,id',
            'events.*.annee_id'  => 'required|exists:annees_academiques,id',
            'events.*.date'     => 'required|date',
            'events.*.heure_debut' => 'required|date_format:H:i',
            'events.*.heure_fin' => 'required|date_format:H:i|after:events.*.heure_debut',
            'events.*.salle'    => 'nullable|string|max:100',
        ]);

        // Vérifier que les filières appartiennent à l'établissement de l'admin
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId) {
            $filiereIds = array_unique(array_column($validated['events'], 'filiere_id'));
            $validFilieres = Filiere::where('etablissement_id', $etablissementId)
                ->whereIn('id', $filiereIds)
                ->pluck('id')
                ->toArray();

            $invalidIds = array_diff($filiereIds, $validFilieres);
            if (!empty($invalidIds)) {
                return $this->errorResponse(
                    'Une ou plusieurs filières ne sont pas autorisées pour votre établissement.',
                    403
                );
            }
        }

        $created = [];
        foreach ($validated['events'] as $eventData) {
            $eventData['statut'] = 'planifie';
            $event = \App\Models\Evenement::create($eventData);
            $created[] = $event;
        }

        return $this->createdResponse([
            'total' => count($created),
            'events' => $created,
        ], count($created) . ' événements créés avec succès.');
    }

    /**
     * Validation et sauvegarde en masse des cours (UEs + ECs) extraits.
     *
     * POST /api/admin/import/validate-courses
     */
    public function validateCourses(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ues'              => 'required|array|min:1',
            'ues.*.code'       => 'required|string|max:20|unique:ues,code',
            'ues.*.intitule'   => 'required|string|max:255',
            'ues.*.filiere_id' => 'required|exists:filieres,id',
            'ues.*.annee_id'   => 'required|exists:annees_academiques,id',
            'ues.*.semestre'   => 'required|integer|min:1|max:6',
            'ues.*.volume_horaire' => 'required|integer|min:1',
            'ues.*.ecs'        => 'nullable|array',
            'ues.*.ecs.*.code' => 'required_with:ues.*.ecs|string|max:20',
            'ues.*.ecs.*.intitule' => 'required_with:ues.*.ecs|string|max:255',
            'ues.*.ecs.*.volume_horaire' => 'required_with:ues.*.ecs|integer|min:1',
        ]);

        // Vérifier que les filières appartiennent à l'établissement de l'admin
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId) {
            $filiereIds = array_unique(array_column($validated['ues'], 'filiere_id'));
            $validFilieres = Filiere::where('etablissement_id', $etablissementId)
                ->whereIn('id', $filiereIds)
                ->pluck('id')
                ->toArray();

            $invalidIds = array_diff($filiereIds, $validFilieres);
            if (!empty($invalidIds)) {
                return $this->errorResponse(
                    'Une ou plusieurs filières ne sont pas autorisées pour votre établissement.',
                    403
                );
            }
        }

        $created = [];
        foreach ($validated['ues'] as $ueData) {
            $ecsData = $ueData['ecs'] ?? [];
            unset($ueData['ecs']);

            $ue = \App\Models\Ue::create($ueData);
            $ecs = [];
            foreach ($ecsData as $ecData) {
                $ecData['ue_id'] = $ue->id;
                $ec = \App\Models\Ec::create($ecData);
                $ecs[] = $ec;
            }

            $ue->load('ecs');
            $created[] = [
                'ue' => $ue,
                'ecs' => $ecs,
            ];
        }

        return $this->createdResponse([
            'total_ues' => count($created),
            'ues' => $created,
        ], count($created) . ' UE(s) créée(s) avec succès.');
    }

    /**
     * Récupération du statut d'une analyse asynchrone.
     * Utilisé par le frontend pour le polling.
     *
     * GET /api/admin/import/analysis-status/{id}
     */
    public function analysisStatus(int $id): JsonResponse
    {
        $analyse = Analyse::find($id);

        if (! $analyse) {
            return $this->errorResponse('Analyse introuvable.', 404);
        }

        return $this->successResponse([
            'analysis_id'        => $analyse->id,
            'type'               => $analyse->type,
            'status'             => $analyse->status,
            'score_de_confiance' => $analyse->score_de_confiance,
            'statut_analyse'     => $analyse->statut_analyse,
            'warning'            => $analyse->warning,
            'error_message'      => $analyse->error_message,
            'result'             => $analyse->status === 'completed' ? $analyse->result : null,
            'created_at'         => $analyse->created_at,
            'updated_at'         => $analyse->updated_at,
        ]);
    }
}
