<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation pour la création d'un étudiant.
     * Conforme CDC 7.1.1 & 7.1.3.
     *
     * @return array<string, string[]>
     */
    public function rules(): array
    {
        return [
            'nom'        => ['required', 'string', 'max:100'],
            'prenom'     => ['required', 'string', 'max:100'],
            // Le matricule est l'identité officielle de l'étudiant et sert à
            // construire son identifiant de connexion : il est obligatoire.
            // Sans lui, un identifiant provisoire (TEMP-xxxx) était généré puis
            // changeait quand le vrai matricule était saisi, invalidant les
            // identifiants déjà communiqués.
            'matricule'  => ['required', 'string', 'max:50', Rule::unique('etudiants', 'matricule')],
            'filiere_id' => ['required', 'integer', 'exists:filieres,id'],
            // annee_id n'est plus attendu : le serveur impose l'année active
            // (voir StudentController::store). Toléré mais ignoré s'il est
            // envoyé, pour ne pas casser les clients existants.
            'annee_id'   => ['nullable', 'integer', 'exists:annees_academiques,id'],
            'email'      => ['required', 'email', Rule::unique('etudiants', 'email')],
        ];
    }

    /**
     * Messages personnalisés en français.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom.required'        => 'Le nom est obligatoire.',
            'prenom.required'     => 'Le prénom est obligatoire.',
            'matricule.required'  => 'Le matricule est obligatoire.',
            'matricule.unique'    => 'Ce matricule existe déjà.',
            'filiere_id.required' => 'La filière est obligatoire.',
            'filiere_id.exists'   => 'La filière sélectionnée n\'existe pas.',
            'annee_id.exists'     => 'L\'année académique sélectionnée n\'existe pas.',
            'email.required'      => 'L\'email est obligatoire.',
            'email.unique'        => 'Cet email est déjà utilisé.',
            'email.email'         => 'Le format de l\'email est invalide.',
        ];
    }
}
