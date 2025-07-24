<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClasseController extends Controller
{
    /**
     * Liste des classes
     */
    public function index(Request $request)
    {
        $query = Classe::with(['enseignantPrincipal', 'eleves.user']);

        // Filtrage par niveau
        if ($request->has('niveau')) {
            $query->where('niveau', $request->niveau);
        }

        // Filtrage par année scolaire
        if ($request->has('annee_scolaire')) {
            $query->where('annee_scolaire', $request->annee_scolaire);
        }

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('niveau', 'like', "%{$search}%");
            });
        }

        $classes = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Liste des classes',
            'data' => $classes
        ]);
    }

    /**
     * Créer une classe
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'niveau' => 'required|string|max:255',
            'annee_scolaire' => 'required|string|max:255',
            'enseignant_principal_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier que l'enseignant principal est bien un enseignant
        if ($request->enseignant_principal_id) {
            $enseignant = User::find($request->enseignant_principal_id);
            if (!$enseignant || $enseignant->role !== 'enseignant') {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'enseignant principal doit avoir le rôle enseignant'
                ], 422);
            }
        }

        $classe = Classe::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Classe créée avec succès',
            'data' => $classe->load('enseignantPrincipal')
        ], 201);
    }

    /**
     * Afficher une classe
     */
    public function show($id)
    {
        $classe = Classe::with(['enseignantPrincipal', 'eleves.user', 'notes.matiere'])
                        ->find($id);

        if (!$classe) {
            return response()->json([
                'success' => false,
                'message' => 'Classe non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Détails de la classe',
            'data' => $classe
        ]);
    }

    /**
     * Mettre à jour une classe
     */
    public function update(Request $request, $id)
    {
        $classe = Classe::find($id);

        if (!$classe) {
            return response()->json([
                'success' => false,
                'message' => 'Classe non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|required|string|max:255',
            'niveau' => 'sometimes|required|string|max:255',
            'annee_scolaire' => 'sometimes|required|string|max:255',
            'enseignant_principal_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier que l'enseignant principal est bien un enseignant
        if ($request->enseignant_principal_id) {
            $enseignant = User::find($request->enseignant_principal_id);
            if (!$enseignant || $enseignant->role !== 'enseignant') {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'enseignant principal doit avoir le rôle enseignant'
                ], 422);
            }
        }

        $classe->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Classe mise à jour avec succès',
            'data' => $classe->load('enseignantPrincipal')
        ]);
    }

    /**
     * Supprimer une classe
     */
    public function destroy($id)
    {
        $classe = Classe::find($id);

        if (!$classe) {
            return response()->json([
                'success' => false,
                'message' => 'Classe non trouvée'
            ], 404);
        }

        // Vérifier qu'il n'y a pas d'élèves dans la classe
        if ($classe->eleves()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer une classe qui contient des élèves'
            ], 422);
        }

        $classe->delete();

        return response()->json([
            'success' => true,
            'message' => 'Classe supprimée avec succès'
        ]);
    }

    /**
     * Élèves d'une classe
     */
    public function eleves($id)
    {
        $classe = Classe::find($id);

        if (!$classe) {
            return response()->json([
                'success' => false,
                'message' => 'Classe non trouvée'
            ], 404);
        }

        $eleves = $classe->eleves()->with(['user', 'notes.matiere'])->get();

        return response()->json([
            'success' => true,
            'message' => 'Élèves de la classe',
            'data' => [
                'classe' => $classe,
                'eleves' => $eleves
            ]
        ]);
    }
}