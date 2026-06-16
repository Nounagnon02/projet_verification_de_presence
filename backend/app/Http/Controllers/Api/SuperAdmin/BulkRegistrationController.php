<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BulkRegistrationController extends Controller
{
    /**
     * Importer en masse des facultés via CSV.
     *
     * Format CSV attendu :
     *   code,nom,email,telephone,adresse
     *   FAST,Faculté des Sciences et Techniques,fast@uac.bj,+229010203,Abomey-Calavi
     *   EPAC,Ecole Polytechnique d'Abomey-Calavi,epac@uac.bj,...
     *
     * POST /api/super-admin/etablissements/import
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('file');
        $rows = array_map('str_getcsv', file($file->getRealPath()));

        if (count($rows) < 2) {
            return $this->errorResponse('Le fichier CSV doit contenir un en-tête et au moins une ligne.', 422);
        }

        $headers = array_map('trim', $rows[0]);
        $expected = ['code', 'nom', 'email'];

        foreach ($expected as $field) {
            if (!in_array($field, $headers)) {
                return $this->errorResponse("La colonne '$field' est requise dans le CSV.", 422);
            }
        }

        $results = ['created' => 0, 'errors' => []];

        for ($i = 1; $i < count($rows); $i++) {
            $data = array_combine($headers, array_map('trim', $rows[$i]));

            try {
                if (Etablissement::where('code', $data['code'])->exists()) {
                    $results['errors'][] = "Ligne $i : Le code '{$data['code']}' existe déjà.";
                    continue;
                }
                if (Etablissement::where('email', $data['email'])->exists()) {
                    $results['errors'][] = "Ligne $i : L'email '{$data['email']}' existe déjà.";
                    continue;
                }

                $etablissement = Etablissement::create([
                    'code'      => $data['code'],
                    'nom'       => $data['nom'] ?? $data['code'],
                    'email'     => $data['email'],
                    'telephone' => $data['telephone'] ?? null,
                    'adresse'   => $data['adresse'] ?? null,
                ]);

                $password = Str::random(12);
                $admin = new User([
                    'name'                => $etablissement->nom,
                    'email'               => $etablissement->email,
                    'role'                => 'faculte_admin',
                    'etablissement_id'    => $etablissement->id,
                    'must_change_password' => true,
                ]);
                $admin->forceFill(['password' => $password])->save();

                try {
                    Mail::to($admin->email)->send(new \App\Mail\WelcomeFaculteAdmin($admin, $password, $etablissement));
                } catch (\Exception $e) {
                    // Silencieux
                }

                $results['created']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Ligne $i : " . $e->getMessage();
            }
        }

        $message = $results['created'] . ' faculté(s) créée(s).';
        if (count($results['errors'])) {
            $message .= ' ' . count($results['errors']) . ' erreur(s).';
        }

        return $this->successResponse($results, $message);
    }
}
