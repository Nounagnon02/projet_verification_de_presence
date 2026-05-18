<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Presence;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * Liste des alertes de fraude potentielle (CDC 11.1)
     */
    public function index()
    {
        $alerts = Presence::with(['etudiant', 'evenement.ec'])
            ->where('statut', 'suspect')
            ->latest()
            ->paginate(15);
            
        return response()->json($alerts);
    }

    /**
     * Valider ou invalider une alerte (CDC 9.2.2)
     */
    public function resolve(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:valide,invalide']);
        
        $presence = Presence::findOrFail($id);
        $presence->update(['statut' => $request->status]);

        return response()->json([
            'message' => 'Alerte résolue avec succès.',
            'presence' => $presence
        ]);
    }
}
