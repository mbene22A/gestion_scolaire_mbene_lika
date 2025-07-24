<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Matiere;
use App\Models\Enseignant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MatiereController extends Controller
{
    /**
     * Liste des matières
     */
    public function index(Request $request)
    {
        $query = Matiere::with('enseignant.user');

        // Filtrage par niveau
        if ($request->has('niveau')) {
            $query->where('niveau', $request->niveau);
        }

        // Filtrage par enseignant
        if ($request->has('enseignant_id')) {
            $query->where('enseignant_id', $request->enseignant_id);
        }

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('niveau', 'like', "%{$search}%");
            });
        }

        $matieres = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Liste des matières',
            'data' => $matieres
        ]);
    }

    /**
     * Créer une matière
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:matieres',
            'coefficient' => 'required|numeric|min:0.5|max:10',
            'niveau' => 'nullable|string|max:255',
            'enseignant_id' => 'required|exists:enseignants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $matiere = Matiere::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Matière créée avec succès',
            'data' => $matiere->load('enseignant.user')
        ], 201);
    }

    /**
     * Afficher une matière
     */
    public function show($id)
    {
        $matiere = Matiere::with(['enseignant.user', 'notes.eleve.user'])
                          ->find($id);

        if (!$matiere) {
            return response()->json([
                'success' => false,
                'message' => 'Matière non trouvée'
            ], 404);
        }

        // Statistiques de la matière
        $stats = [
            'nombre_notes' => $matiere->notes()->count(),
            'moyenne_generale' => round($matiere->notes()->avg('valeur') ?? 0, 2),
            'nombre_eleves' => $matiere->notes()->distinct('eleve_id')->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Détails de la matière',
            'data' => [
                'matiere' => $matiere,
                'statistiques' => $stats
            ]
        ]);
    }

    /**
     * Mettre à jour une matière
     */
    public function update(Request $request, $id)
    {
        $matiere = Matiere::find($id);

        if (!$matiere) {
            return response()->json([
                'success' => false,
                'message' => 'Matière non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:255|unique:matieres,code,' . $id,
            'coefficient' => 'sometimes|required|numeric|min:0.5|max:10',
            'niveau' => 'sometimes|nullable|string|max:255',
            'enseignant_id' => 'sometimes|required|exists:enseignants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $matiere->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Matière mise à jour avec succès',
            'data' => $matiere->load('enseignant.user')
        ]);
    }

    /**
     * Supprimer une matière
     */
    public function destroy($id)
    {
        $matiere = Matiere::find($id);

        if (!$matiere) {
            return response()->json([
                'success' => false,
                'message' => 'Matière non trouvée'
            ], 404);
        }

        // Vérifier qu'il n'y a pas de notes pour cette matière
        if ($matiere->notes()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer une matière qui a des notes'
            ], 422);
        }

        $matiere->delete();

        return response()->json([
            'success' => true,
            'message' => 'Matière supprimée avec succès'
        ]);
    }
}