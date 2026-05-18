<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\AnneeAcademique;
use App\Mail\StudentRegisteredMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    /**
     * Inscription individuelle (US01).
     * Conforme CDC 7.1.1 & 7.1.3
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'matricule' => 'required|string|unique:etudiants,matricule',
            'filiere_id' => 'required|exists:filieres,id',
            'annee_id' => 'required|exists:annees_academiques,id',
            'email' => 'required|email|unique:etudiants,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $filiere = Filiere::findOrFail($request->filiere_id);
        $annee = AnneeAcademique::findOrFail($request->annee_id);

        // Logique déterministe CDC 7.1.3
        $identifiantUnique = $this->generateDeterministicId(
            $request->nom, 
            $request->prenom, 
            $request->matricule, 
            $filiere->code, 
            $annee->libelle
        );

        $etudiant = Etudiant::create([
            'id' => (string) Str::uuid(),
            'nom' => mb_strtoupper($this->removeAccents($request->nom)),
            'prenom' => mb_strtoupper($this->removeAccents($request->prenom)),
            'matricule' => $request->matricule,
            'filiere_id' => $request->filiere_id,
            'annee_id' => $request->annee_id,
            'email' => $request->email,
            'identifiant_unique' => $identifiantUnique,
        ]);

        // Envoi de l'e-mail (CDC 2.2)
        try {
            Mail::to($etudiant->email)->send(new StudentRegisteredMail($etudiant));
        } catch (\Exception $e) {
            // Log error but don't fail registration
            \Log::error("Email failed for {$etudiant->email}: " . $e->getMessage());
        }

        return response()->json([
            'message' => 'Étudiant inscrit avec succès et e-mail envoyé.',
            'data' => $etudiant
        ], 201);
    }

    /**
     * Génère l'identifiant selon la logique CDC 7.1.3
     */
    private function generateDeterministicId($nom, $prenom, $matricule, $filiere, $annee)
    {
        $nom = $this->sanitize($nom);
        $prenom = $this->sanitize($prenom);
        $matricule = $this->sanitize($matricule);
        $filiere = $this->sanitize($filiere);
        $annee = $this->sanitize($annee);

        return "{$nom}_{$prenom}_{$matricule}_{$filiere}_{$annee}";
    }

    private function sanitize($string)
    {
        $string = $this->removeAccents($string);
        $string = mb_strtoupper($string);
        $string = str_replace([' ', '-'], '_', $string);
        return $string;
    }

    private function removeAccents($string)
    {
        return strtr(utf8_decode($string), 
            utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 
            'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
    }
}
