<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Trait de réponse unifiée pour toutes les API RESTful.
 *
 * Centralise le format de réponse JSON de l'application,
 * garantissant une structure cohérente : {success, message, data, errors}.
 */
trait ApiResponse
{
    /**
     * Réponse de succès.
     *
     * @param  mixed       $data    Données à retourner
     * @param  string      $message Message de confirmation
     * @param  int         $code    Code HTTP (défaut : 200)
     * @param  array       $headers En-têtes supplémentaires
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Opération réussie.',
        int $code = 200,
        array $headers = []
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code, $headers);
    }

    /**
     * Réponse de création (201).
     *
     * @param  mixed  $data    Données créées
     * @param  string $message Message de confirmation
     */
    protected function createdResponse(mixed $data, string $message = 'Ressource créée avec succès.'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Réponse d'erreur.
     *
     * @param  string      $message Message d'erreur
     * @param  int         $code    Code HTTP (défaut : 400)
     * @param  mixed|null  $errors  Détails des erreurs (ex: erreurs de validation)
     */
    protected function errorResponse(
        string $message = 'Erreur interne.',
        int $code = 400,
        mixed $errors = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Réponse 404 — ressource non trouvée.
     */
    protected function notFoundResponse(string $message = 'Ressource non trouvée.'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Réponse 403 — accès refusé.
     */
    protected function forbiddenResponse(string $message = 'Action non autorisée.'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Réponse 422 — erreur de validation.
     *
     * @param  mixed  $errors  Erreurs de validation
     * @param  string $message Message d'erreur
     */
    protected function validationErrorResponse(
        mixed $errors,
        string $message = 'Erreur de validation.'
    ): JsonResponse {
        return $this->errorResponse($message, 422, $errors);
    }

    /**
     * Réponse 409 — conflit (ex: doublon, fraude).
     */
    protected function conflictResponse(string $message = 'Conflit détecté.'): JsonResponse
    {
        return $this->errorResponse($message, 409);
    }

    /**
     * Réponse 410 — ressource expirée (ex: QR code).
     */
    protected function goneResponse(string $message = 'Ressource expirée.'): JsonResponse
    {
        return $this->errorResponse($message, 410);
    }

    /**
     * Réponse paginée (pour les collections).
     *
     * @param  mixed   $collection Collection paginée (LengthAwarePaginator)
     * @param  string  $message    Message de confirmation
     */
    protected function paginatedResponse(mixed $collection, string $message = 'Liste récupérée.'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $collection->items(),
            'meta'    => [
                'current_page' => $collection->currentPage(),
                'last_page'    => $collection->lastPage(),
                'per_page'     => $collection->perPage(),
                'total'        => $collection->total(),
            ],
        ]);
    }
}
