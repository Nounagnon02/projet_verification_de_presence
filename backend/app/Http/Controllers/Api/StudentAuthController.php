<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StudentAuthController extends Controller
{
    /**
     * Authentifie un étudiant avec son email et son identifiant unique.
     *
     * POST /api/auth/student/login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'              => 'required|email',
            'identifiant_unique' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou identifiant invalide.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $etudiant = Etudiant::where('email', $request->email)
            ->where('identifiant_unique', $request->identifiant_unique)
            ->first();

        if (!$etudiant) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants invalides. Vérifiez votre email et votre identifiant unique.',
            ], 422);
        }

        // Révoquer les anciens tokens de cet étudiant
        $etudiant->tokens()->delete();

        // Créer un token Sanctum
        $token = $etudiant->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connecté avec succès.',
            'data'    => [
                'user'  => [
                    'id'                 => $etudiant->id,
                    'nom'                => $etudiant->nom,
                    'prenom'             => $etudiant->prenom,
                    'email'              => $etudiant->email,
                    'matricule'          => $etudiant->matricule,
                    'identifiant_unique' => $etudiant->identifiant_unique,
                    'role'               => 'etudiant',
                    'filiere_id'         => $etudiant->filiere_id,
                    'annee_id'           => $etudiant->annee_id,
                ],
                'token' => $token,
            ],
        ]);
    }

    /**
     * Retourne l'étudiant connecté via son token.
     *
     * GET /api/auth/student/me
     */
    public function me(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié.',
            ], 401);
        }

        // Extraire l'ID du token (format Sanctum: "1|plaintext")
        $parts = explode('|', $token);
        $tokenId = $parts[0] ?? null;

        if (!$tokenId) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide.',
            ], 401);
        }

        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::find($tokenId);

        if (!$personalAccessToken || !$personalAccessToken->tokenable instanceof Etudiant) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide ou expiré.',
            ], 401);
        }

        $etudiant = $personalAccessToken->tokenable;

        return response()->json([
            'success' => true,
            'message' => 'Profil récupéré.',
            'data'    => [
                'id'                 => $etudiant->id,
                'nom'                => $etudiant->nom,
                'prenom'             => $etudiant->prenom,
                'email'              => $etudiant->email,
                'matricule'          => $etudiant->matricule,
                'identifiant_unique' => $etudiant->identifiant_unique,
                'role'               => 'etudiant',
                'filiere_id'         => $etudiant->filiere_id,
                'annee_id'           => $etudiant->annee_id,
            ],
        ]);
    }

    /**
     * Déconnecte l'étudiant (révoque le token).
     *
     * POST /api/auth/student/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if ($token) {
            $parts = explode('|', $token);
            $tokenId = $parts[0] ?? null;

            if ($tokenId) {
                $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::find($tokenId);
                if ($personalAccessToken) {
                    $personalAccessToken->delete();
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Déconnecté avec succès.',
        ]);
    }
}
