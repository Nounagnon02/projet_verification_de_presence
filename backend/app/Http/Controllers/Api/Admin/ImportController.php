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
}
