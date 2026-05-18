<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    protected $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    /**
     * Importation des étudiants via CSV (US02).
     */
    public function students(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle, 1000, ','); // Supposer séparateur virgule

        $results = ['success' => 0, 'errors' => []];

        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            $data = array_combine($header, $row);
            
            $validator = Validator::make($data, [
                'nom' => 'required',
                'prenom' => 'required',
                'matricule' => 'required|unique:etudiants,matricule',
                'filiere_code' => 'required|exists:filieres,code',
                'annee_libelle' => 'required|exists:annees_academiques,libelle',
                'email' => 'required|email|unique:etudiants,email',
            ]);

            if ($validator->fails()) {
                $results['errors'][] = ['row' => $data, 'errors' => $validator->errors()];
                continue;
            }

            $filiere = Filiere::where('code', $data['filiere_code'])->first();
            $annee = AnneeAcademique::where('libelle', $data['annee_libelle'])->first();

            $identifiantUnique = strtoupper(substr(md5($data['matricule'] . config('app.key')), 0, 8));

            Etudiant::create([
                'id' => Str::uuid(),
                'nom' => strtoupper($data['nom']),
                'prenom' => ucfirst($data['prenom']),
                'matricule' => $data['matricule'],
                'filiere_id' => $filiere->id,
                'annee_id' => $annee->id,
                'email' => $data['email'],
                'identifiant_unique' => $identifiantUnique,
            ]);

            $results['success']++;
        }

        fclose($handle);

        return response()->json([
            'message' => "Importation terminée: {$results['success']} succès.",
            'results' => $results
        ]);
    }

    /**
     * Importation de l'emploi du temps via PDF (US03 - Gemini IA).
     */
    public function schedule(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:pdf']);
        
        $path = $request->file('file')->store('temp');
        $analysis = $this->gemini->analyzeSchedule(storage_path('app/' . $path));

        return response()->json([
            'message' => 'Analyse terminée par l\'IA.',
            'data' => $analysis['data']
        ]);
    }
}
