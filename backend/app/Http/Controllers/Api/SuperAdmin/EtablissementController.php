<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EtablissementController extends Controller
{
    public function index(): JsonResponse
    {
        $etablissements = Etablissement::withCount([
            'filieres',
            'users' => fn ($q) => $q->where('role', 'faculte_admin'),
        ])->orderBy('nom')->get();

        return $this->successResponse($etablissements);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'      => 'required|string|max:20|unique:etablissements,code',
            'nom'       => 'required|string|max:255',
            'email'     => 'required|email|max:255|unique:etablissements,email',
            'telephone' => 'nullable|string|max:20',
            'adresse'   => 'nullable|string',
            'logo'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = Storage::disk('supabase')
                ->putFile('logos', $request->file('logo'), 'public');
        }

        $etablissement = Etablissement::create($validated);

        // Créer automatiquement un admin faculté
        $password = Str::random(12);
        $admin = new User([
            'name'                => $validated['nom'],
            'email'               => $validated['email'],
            'role'                => 'faculte_admin',
            'etablissement_id'    => $etablissement->id,
            'must_change_password' => true,
        ]);
        $admin->forceFill(['password' => $password])->save();

        // Envoyer l'email de bienvenue (si mail configuré)
        try {
            Mail::to($admin->email)->send(new \App\Mail\WelcomeFaculteAdmin($admin, $password, $etablissement));
        } catch (\Exception $e) {
            // Silencieux en dev — le mail sera loggé
        }

        return $this->createdResponse([
            'etablissement' => $etablissement,
            'admin'         => [
                'email'    => $admin->email,
                'password' => $password, // Uniquement à la création
            ],
        ], 'Faculté créée avec succès. Les identifiants ont été envoyés par email.');
    }

    public function show(Etablissement $etablissement): JsonResponse
    {
        $etablissement->loadCount(['filieres', 'users']);
        $stats = [
            'total_etudiants' => \App\Models\Etudiant::whereIn('filiere_id',
                $etablissement->filieres()->pluck('id')
            )->count(),
            'total_presences' => \App\Models\Presence::whereIn('evenement_id',
                \App\Models\Evenement::whereIn('filiere_id',
                    $etablissement->filieres()->pluck('id')
                )->pluck('id')
            )->count(),
        ];

        return $this->successResponse(array_merge($etablissement->toArray(), $stats));
    }

    public function update(Request $request, Etablissement $etablissement): JsonResponse
    {
        $validated = $request->validate([
            'code'      => 'sometimes|string|max:20|unique:etablissements,code,' . $etablissement->id,
            'nom'       => 'sometimes|string|max:255',
            'email'     => 'sometimes|email|max:255|unique:etablissements,email,' . $etablissement->id,
            'telephone' => 'nullable|string|max:20',
            'adresse'   => 'nullable|string',
            'actif'     => 'boolean',
            'logo'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            // Supprimer l'ancien logo
            if ($etablissement->logo) {
                Storage::disk('supabase')->delete($etablissement->logo);
            }
            $validated['logo'] = Storage::disk('supabase')
                ->putFile('logos', $request->file('logo'), 'public');
        }

        $etablissement->update($validated);

        // Synchroniser l'email de l'admin faculté si l'email de l'établissement change
        if (isset($validated['email'])) {
            User::where('etablissement_id', $etablissement->id)
                ->where('role', 'faculte_admin')
                ->update(['email' => $validated['email']]);
        }

        return $this->successResponse($etablissement, 'Faculté mise à jour.');
    }

    public function destroy(Etablissement $etablissement): JsonResponse
    {
        $etablissement->delete();
        return $this->successResponse(null, 'Faculté supprimée.');
    }

    /**
     * Stats détaillées d'une faculté.
     */
    public function stats(Etablissement $etablissement): JsonResponse
    {
        $filiereIds = $etablissement->filieres()->pluck('id');
        $totalEtudiants = \App\Models\Etudiant::whereIn('filiere_id', $filiereIds)->count();
        $totalPresences = \App\Models\Presence::whereIn('evenement_id',
            \App\Models\Evenement::whereIn('filiere_id', $filiereIds)->pluck('id')
        )->count();
        $coursAujourdhui = \App\Models\Evenement::whereIn('filiere_id', $filiereIds)
            ->whereDate('date', today())->count();

        return $this->successResponse([
            'total_etudiants'   => $totalEtudiants,
            'total_presences'   => $totalPresences,
            'cours_aujourdhui'  => $coursAujourdhui,
            'total_filieres'    => $filiereIds->count(),
        ]);
    }

    /**
     * Renvoyer les credentials par email.
     */
    public function resendCredentials(Etablissement $etablissement): JsonResponse
    {
        $admin = User::where('etablissement_id', $etablissement->id)
            ->where('role', 'faculte_admin')
            ->first();

        if (!$admin) {
            return $this->errorResponse('Aucun admin trouvé pour cette faculté.', 404);
        }

        $password = Str::random(12);
        $admin->forceFill([
            'password'            => $password,
            'must_change_password' => true,
        ])->save();

        try {
            Mail::to($admin->email)->send(new \App\Mail\WelcomeFaculteAdmin($admin, $password, $etablissement));
        } catch (\Exception $e) {
            // Silencieux
        }

        return $this->successResponse([
            'email'    => $admin->email,
            'password' => $password,
        ], 'Nouveaux identifiants envoyés par email.');
    }
}
