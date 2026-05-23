<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation pour la mise à jour d'un étudiant.
     *
     * @return array<string, string[]>
     */
    public function rules(): array
    {
        $etudiantId = $this->route('student');

        return [
            'nom'        => ['sometimes', 'string', 'max:100'],
            'prenom'     => ['sometimes', 'string', 'max:100'],
            'matricule'  => ['sometimes', 'string', Rule::unique('etudiants', 'matricule')->ignore($etudiantId)],
            'filiere_id' => ['sometimes', 'integer', 'exists:filieres,id'],
            'annee_id'   => ['sometimes', 'integer', 'exists:annees_academiques,id'],
            'email'      => ['sometimes', 'email', Rule::unique('etudiants', 'email')->ignore($etudiantId)],
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
            'matricule.unique' => 'Ce matricule est déjà pris par un autre étudiant.',
            'email.unique'     => 'Cet email est déjà utilisé par un autre étudiant.',
            'email.email'      => 'Le format de l\'email est invalide.',
            'filiere_id.exists' => 'La filière sélectionnée n\'existe pas.',
            'annee_id.exists'  => 'L\'année académique sélectionnée n\'existe pas.',
        ];
    }
}
