<?php

namespace App\Jobs;

use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Services\IdentifiantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportStudentsCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 300;

    protected $filePath;
    protected $userId;

    public function __construct(string $filePath, int $userId)
    {
        $this->filePath = $filePath;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        $handle = fopen(storage_path('app/' . $this->filePath), 'r');
        $header = fgetcsv($handle, 1000, ',');

        $results = ['success' => 0, 'errors' => []];

        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            $data = array_combine($header, $row);
            
            $validator = Validator::make($data, [
                'nom' => 'required|string|max:255',
                'prenom' => 'required|string|max:255',
                'matricule' => 'required|string|unique:etudiants,matricule',
                'filiere_code' => 'required|exists:filieres,code',
                'annee_libelle' => 'required|exists:annees_academiques,libelle',
                'email' => 'required|email|unique:etudiants,email',
            ]);

            if ($validator->fails()) {
                $results['errors'][] = [
                    'row' => $data,
                    'errors' => $validator->errors()->toArray()
                ];
                continue;
            }

            try {
                $filiere = Filiere::where('code', $data['filiere_code'])->first();
                $annee = AnneeAcademique::where('libelle', $data['annee_libelle'])->first();

                $identifiantUnique = IdentifiantService::generate(
                    $data['nom'],
                    $data['prenom'],
                    $data['matricule'],
                    $filiere->id,
                    $annee->id
                );

                $etudiant = Etudiant::create([
                    'id' => Str::uuid(),
                    'nom' => strtoupper($data['nom']),
                    'prenom' => ucfirst($data['prenom']),
                    'matricule' => $data['matricule'],
                    'filiere_id' => $filiere->id,
                    'annee_id' => $annee->id,
                    'email' => $data['email'],
                    'identifiant_unique' => $identifiantUnique,
                ]);

                // Envoyer l'email de manière asynchrone
                SendIdentifiantEmailJob::dispatch($etudiant);

                $results['success']++;
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'row' => $data,
                    'errors' => ['exception' => $e->getMessage()]
                ];
                Log::error("Erreur import étudiant: " . $e->getMessage());
            }
        }

        fclose($handle);

        Log::info("Import CSV terminé: {$results['success']} succès, " . count($results['errors']) . " erreurs");
        
        // TODO: Notifier l'admin du résultat via notification
    }
}
