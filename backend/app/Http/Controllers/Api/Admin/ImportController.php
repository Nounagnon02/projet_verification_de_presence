<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportController extends Controller
{
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

            Etudiant::create([
                'id'                 => (string) Str::uuid(),
                'nom'                => mb_strtoupper($data['nom']),
                'prenom'             => mb_strtoupper($data['prenom']),
                'matricule'          => $data['matricule'],
                'filiere_id'         => $filiere->id,
                'annee_id'           => $annee->id,
                'email'              => $data['email'],
                'identifiant_unique' => strtoupper(
                    $data['nom'] . '_' . $data['prenom'] . '_' . $data['matricule'] . '_' . $filiere->code . '_' . $annee->libelle
                ),
            ]);

            $results['success']++;
        }

        fclose($handle);

        return $this->successResponse(
            $results,
            "Importation terminée : {$results['success']}/{$results['total']} étudiant(s) importé(s)."
        );
    }

    /**
     * Importation et analyse des cours (UEs/ECs) via PDF (Gemini IA).
     *
     * POST /api/admin/import/courses
     */
    public function courses(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'file' => 'required|file|mimes:pdf|max:20480',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $path     = $request->file('file')->store('imports/courses');
        $analysis = $this->gemini->analyzeCourses(storage_path('app/' . $path));

        return $this->successResponse($analysis, 'Analyse des cours terminée.');
    }

    /**
     * Importation et analyse de l'emploi du temps via PDF (US03 - Gemini IA).
     * Conforme CDC 8.1 & 8.2.
     *
     * POST /api/admin/import/schedule
     */
    public function schedule(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'file' => 'required|file|mimes:pdf|max:20480',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $path     = $request->file('file')->store('imports/schedule');
        $analysis = $this->gemini->analyzeSchedule(storage_path('app/' . $path));

        return $this->successResponse($analysis, 'Analyse de l\'emploi du temps terminée.');
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
}
