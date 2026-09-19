<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalleResource;
use App\Models\Etablissement;
use App\Models\Filiere;
use App\Models\Salle;
use App\Services\CorrespondanceSalles;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalleController extends Controller
{
    use ScopedByEtablissement;

    public function index(Request $request): JsonResponse
    {
        $query = Salle::with('etablissement')
            // Usage de chaque salle : c'est par les salles réellement utilisées
            // qu'il faut commencer à configurer le GPS et le Wi-Fi.
            ->withCount([
                'evenements as seances_a_venir' => fn ($q) => $q->where('date', '>=', today())
                    ->whereIn('statut', ['planifie', 'en_cours']),
                'emploisDuTemps as creneaux_count',
            ]);

        // Scope par établissement
        $this->scopeQuery($query, $request);

        if ($request->filled('search')) {
            // ilike : sous PostgreSQL, like distingue la casse, et « amphi » ne
            // trouvait pas « Amphi C ».
            $search = '%' . addcslashes((string) $request->search, '%_\\') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'ilike', $search)
                  ->orWhere('code', 'ilike', $search);
            });
        }

        if ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        $salles = $query->orderBy('nom')->get();

        return $this->successResponse(SalleResource::collection($salles));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'              => 'required|string|max:255',
            // Facultatif : dérivé du nom s'il est laissé vide, comme pour les
            // salles créées par les imports.
            'code'             => 'nullable|string|max:50|unique:salles,code',
            'etablissement_id' => 'nullable|exists:etablissements,id',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',
            'rayon_geofence_m' => 'nullable|integer|min:5|max:500',
            'ssid_attendu'     => 'nullable|string|max:255',
            'bssid_attendu'    => 'nullable|string|max:17',
            'ip_range'         => 'nullable|string|max:50',
            'hors_reseau'      => 'boolean',
            'actif'            => 'boolean',
        ]);

        // Vérifier scoping établissement
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId && !empty($validated['etablissement_id']) && (int) $validated['etablissement_id'] !== $etablissementId) {
            return $this->errorResponse('Vous ne pouvez créer une salle que pour votre établissement.', 403);
        }

        // Un admin de faculté crée dans son établissement sans avoir à le dire.
        $validated['etablissement_id'] = $validated['etablissement_id'] ?? $etablissementId;

        if (!$validated['etablissement_id']) {
            return $this->errorResponse("Précisez l'établissement de la salle.", 422);
        }

        $validated['code'] = $validated['code'] ?? CorrespondanceSalles::codeUnique($validated['nom']);

        $salle = Salle::create($validated);

        return $this->createdResponse(new SalleResource($salle), 'Salle créée avec succès.');
    }

    public function show(Request $request, Salle $salle): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId && $salle->etablissement_id !== $etablissementId) {
            return $this->errorResponse('Salle non trouvée.', 404);
        }

        $salle->load(['etablissement', 'evenements' => fn ($q) => $q->with('ec')->orderBy('date', 'desc')->limit(10)]);

        return $this->successResponse(new SalleResource($salle));
    }

    public function update(Request $request, Salle $salle): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId && $salle->etablissement_id !== $etablissementId) {
            return $this->errorResponse('Salle non trouvée.', 404);
        }

        $validated = $request->validate([
            'nom'              => 'sometimes|string|max:255',
            'code'             => 'sometimes|string|max:50|unique:salles,code,' . $salle->id,
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',
            'rayon_geofence_m' => 'nullable|integer|min:5|max:500',
            'ssid_attendu'     => 'nullable|string|max:255',
            'bssid_attendu'    => 'nullable|string|max:17',
            'ip_range'         => 'nullable|string|max:50',
            'hors_reseau'      => 'boolean',
            'actif'            => 'boolean',
        ]);

        $salle->update($validated);

        return $this->successResponse(new SalleResource($salle), 'Salle mise à jour.');
    }

    public function destroy(Request $request, Salle $salle): JsonResponse
    {
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId && $salle->etablissement_id !== $etablissementId) {
            return $this->errorResponse('Salle non trouvée.', 404);
        }

        // Vérifier qu'aucun événement futur n'utilise cette salle
        $evenementsFuturs = $salle->evenements()->where('date', '>=', now())->count();
        if ($evenementsFuturs > 0) {
            return $this->errorResponse(
                "Impossible de supprimer : {$evenementsFuturs} événement(s) futur(s) utilise(nt) cette salle.",
                422
            );
        }

        $salle->delete();

        return $this->successResponse(null, 'Salle supprimée.');
    }

    /**
     * Obtenir les salles disponibles pour un établissement (pour select dans les formulaires).
     */
    public function disponibles(Request $request): JsonResponse
    {
        $query = Salle::where('actif', true);

        $this->scopeQuery($query, $request);

        $salles = $query->orderBy('nom')->get()->map(fn (Salle $s) => $this->resume($s));

        return $this->successResponse($salles);
    }

    /**
     * Salles reconnues pour des noms lus dans un document : pré-remplit l'écran
     * de validation d'un import. La reconnaissance est celle des imports
     * (CorrespondanceSalles), limitée à l'établissement de la filière de
     * destination.
     *
     * POST /api/admin/salles/reconnaitre  { filiere_id, noms: string[] }
     */
    public function reconnaitre(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filiere_id' => 'required|integer|exists:filieres,id',
            'noms'       => 'present|array|max:500',
            'noms.*'     => 'nullable|string|max:255',
        ]);

        $filiere = $this->filiereAutorisee($request, (int) $validated['filiere_id']);
        $correspondance = $filiere->etablissement_id ? CorrespondanceSalles::pour($filiere->etablissement_id) : null;

        $resultat = collect($validated['noms'])
            ->filter(fn ($nom) => is_string($nom) && trim($nom) !== '')
            ->unique()
            ->values()
            ->map(function (string $nom) use ($correspondance) {
                $salle = $correspondance?->trouver($nom);

                return [
                    'nom'        => $nom,
                    // Une salle désactivée n'est pas proposée : l'écran le dit.
                    'salle'      => $salle && $salle->actif ? $this->resume($salle) : null,
                    'desactivee' => $salle !== null && !$salle->actif,
                ];
            });

        return $this->successResponse($resultat);
    }

    /**
     * Crée, en un clic depuis l'écran de validation d'un import, la salle
     * qu'un document nomme et que l'établissement n'a pas encore déclarée. Si
     * elle existe déjà sous ce nom, elle est rendue telle quelle : deux clics ne
     * créent pas deux salles.
     *
     * POST /api/admin/salles/depuis-nom  { filiere_id, nom }
     */
    public function depuisNom(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filiere_id' => 'required|integer|exists:filieres,id',
            'nom'        => 'required|string|max:255',
        ]);

        $filiere = $this->filiereAutorisee($request, (int) $validated['filiere_id']);

        if (!$filiere->etablissement_id) {
            return $this->errorResponse("La filière {$filiere->code} n'est rattachée à aucun établissement : impossible d'y créer une salle.", 422);
        }

        if (CorrespondanceSalles::cle($validated['nom']) === '') {
            return $this->errorResponse('Ce nom ne désigne aucune salle.', 422);
        }

        [$salle, $creee] = CorrespondanceSalles::pour($filiere->etablissement_id)->trouverOuCreer($validated['nom']);

        if (!$salle->actif) {
            return $this->errorResponse("La salle « {$salle->nom} » existe mais elle est désactivée : réactivez-la dans Paramètres > Salles.", 422);
        }

        return $creee
            ? $this->createdResponse($this->resume($salle), "Salle « {$salle->nom} » créée. Elle ne vérifie que le QR code : GPS et Wi-Fi à configurer dans Paramètres > Salles.")
            : $this->successResponse($this->resume($salle), "La salle « {$salle->nom} » existait déjà.");
    }

    /** Filière de destination, 404 si elle relève d'un autre établissement. */
    private function filiereAutorisee(Request $request, int $filiereId): Filiere
    {
        $filiere = Filiere::findOrFail($filiereId);

        $this->authorizeEtablissement($filiere, $request);

        return $filiere;
    }

    /**
     * Ce que chaque salle vérifie réellement au scan est joint : les écrans
     * peuvent ainsi dire « QR seul » d'une salle déclarée sans coordonnées ni
     * réseau, au lieu de laisser croire qu'elle est protégée.
     *
     * @return array<string, mixed>
     */
    private function resume(Salle $s): array
    {
        return [
            'id'               => $s->id,
            'nom'              => $s->nom,
            'code'             => $s->code,
            'etablissement_id' => $s->etablissement_id,
            'verifie_gps'      => $s->verifieGps(),
            'verifie_wifi'     => $s->verifieWifi(),
        ];
    }
}
