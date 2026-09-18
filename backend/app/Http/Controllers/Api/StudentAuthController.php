<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class StudentAuthController extends Controller
{
    /**
     * Message unique de refus. Distinguer « email inconnu », « identifiant
     * erroné » et « code erroné » transformerait la connexion en oracle : le
     * premier message dirait quel étudiant existe, le second confirmerait un
     * identifiant deviné. Un seul et même texte pour les trois.
     */
    private const REFUS = 'Identifiants invalides.';

    /**
     * Authentifie un étudiant avec son email, son identifiant unique et son
     * code d'accès.
     *
     * Le code d'accès est le seul des trois à être secret : l'identifiant
     * unique est déterministe (NOM_PRENOM_MATRICULE_FILIERE_ANNEE, voir
     * IdentifiantService) et l'email suit la convention de l'université. Sans
     * ce troisième facteur, un camarade de promotion reconstituait le couple de
     * toutes pièces et pointait à la place d'un absent.
     *
     * POST /api/auth/student/login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'              => 'required|email',
            'identifiant_unique' => 'required|string',
            'code'               => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => self::REFUS,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $etudiant = Etudiant::where('email', $request->email)
            ->where('identifiant_unique', $request->identifiant_unique)
            ->first();

        if (!$etudiant) {
            return response()->json([
                'success' => false,
                'message' => self::REFUS,
            ], 422);
        }

        // Étudiant inscrit avant la mise en place du code : sans ce cas
        // distinct, il lirait « identifiants invalides » et chercherait
        // indéfiniment une faute de frappe dans des identifiants pourtant
        // exacts. Le code lui est attribué par « etudiants:codes-acces
        // --envoyer » ou par le renvoi d'identifiants côté administration.
        if (!$etudiant->code_acces) {
            return response()->json([
                'success' => false,
                'code'    => 'code_absent',
                'message' => "Aucun code d'accès n'a encore été envoyé. Demandez-le à votre administration.",
            ], 409);
        }

        if (!Hash::check((string) $request->input('code'), $etudiant->code_acces)) {
            return response()->json([
                'success' => false,
                'message' => self::REFUS,
            ], 422);
        }

        // Révoquer les anciens tokens de cet étudiant
        $etudiant->tokens()->delete();

        // Capacité explicite « etudiant ». Sans elle, le token porterait « * » et
        // franchirait les contrôles des routes d'administration : auth:sanctum
        // authentifie indifféremment un utilisateur et un étudiant.
        $token = $etudiant->createToken('mobile-app', ['etudiant'])->plainTextToken;

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
                    'est_responsable'    => (bool) $etudiant->est_responsable,
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
        // L'authentification est faite par le middleware auth:sanctum, qui
        // vérifie le hash du token. Ne jamais résoudre un token par son seul
        // ID : l'ID est public et la partie secrète ne serait pas contrôlée.
        $etudiant = $request->user();

        if (!$etudiant instanceof Etudiant) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide ou expiré.',
            ], 401);
        }

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
                'est_responsable'    => (bool) $etudiant->est_responsable,
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
        // Ne révoque que le token présenté, après vérification par Sanctum.
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnecté avec succès.',
        ]);
    }
}
