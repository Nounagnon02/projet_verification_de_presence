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
            'matricule'  => ['nullable', 'string', Rule::unique('etudiants', 'matricule')],
            'filiere_id' => ['required', 'integer', 'exists:filieres,id'],
            'annee_id'   => ['required', 'integer', 'exists:annees_academiques,id'],
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
            'annee_id.required'   => 'L\'année académique est obligatoire.',
            'annee_id.exists'     => 'L\'année académique sélectionnée n\'existe pas.',
            'email.required'      => 'L\'email est obligatoire.',
            'email.unique'        => 'Cet email est déjà utilisé.',
            'email.email'         => 'Le format de l\'email est invalide.',
        ];
    }
}
