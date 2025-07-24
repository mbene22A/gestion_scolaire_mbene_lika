<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Note;
use App\Models\Bulletin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    /**
     * Dashboard administrateur
     */
    public function dashboard()
    {
        $stats = [
            'total_eleves' => Eleve::count(),
            'total_enseignants' => Enseignant::count(),
            'total_classes' => Classe::count(),
            'total_bulletins_publies' => Bulletin::where('publie', true)->count(),
            'eleves_par_classe' => Classe::withCount('eleves')->get(),
            'bulletins_recents' => Bulletin::with('eleve.user')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
            'notes_recentes' => Note::with(['eleve.user', 'matiere'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
        ];

        return response()->json([
            'success' => true,
            'message' => 'Dashboard administrateur',
            'data' => $stats
        ]);
    }

    /**
     * Liste des utilisateurs
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Filtrage par rôle
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->with(['enseignant', 'eleve.classe'])
                      ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Liste des utilisateurs',
            'data' => $users
        ]);
    }

    /**
     * Créer un utilisateur
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|in:administrateur,enseignant,eleve',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès',
            'data' => $user
        ], 201);
    }

    /**
     * Afficher un utilisateur
     */
    public function show($id)
    {
        $user = User::with(['enseignant', 'eleve.classe'])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Détails de l\'utilisateur',
            'data' => $user
        ]);
    }

    /**
     * Mettre à jour un utilisateur
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|required|string|max:255',
            'prenom' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'sometimes|required|string|min:6',
            'role' => 'sometimes|required|in:administrateur,enseignant,eleve',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['nom', 'prenom', 'email', 'role']);
        
        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur mis à jour avec succès',
            'data' => $user
        ]);
    }

    /**
     * Supprimer un utilisateur
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        // Empêcher la suppression de soi-même
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas supprimer votre propre compte'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur supprimé avec succès'
        ]);
    }

    /**
     * Statistiques générales
     */
    public function statsOverview()
    {
        $stats = [
            'users' => [
                'total' => User::count(),
                'administrateurs' => User::where('role', 'administrateur')->count(),
                'enseignants' => User::where('role', 'enseignant')->count(),
                'eleves' => User::where('role', 'eleve')->count(),
            ],
            'academique' => [
                'classes' => Classe::count(),
                'notes_totales' => Note::count(),
                'bulletins_publies' => Bulletin::where('publie', true)->count(),
                'moyenne_generale' => round(Note::avg('valeur'), 2),
            ],
            'activite_recente' => [
                'nouveaux_users_semaine' => User::where('created_at', '>=', now()->subWeek())->count(),
                'notes_ajoutees_semaine' => Note::where('created_at', '>=', now()->subWeek())->count(),
                'bulletins_publies_semaine' => Bulletin::where('publie', true)
                    ->where('updated_at', '>=', now()->subWeek())->count(),
            ]
        ];

        return response()->json([
            'success' => true,
            'message' => 'Statistiques générales',
            'data' => $stats
        ]);
    }

    /**
     * Statistiques par classe
     */
    public function statsClasses()
    {
        $classes = Classe::withCount('eleves')
            ->with(['eleves' => function($query) {
                $query->with('notes');
            }])
            ->get()
            ->map(function($classe) {
                $moyenneClasse = $classe->eleves->flatMap->notes->avg('valeur');
                return [
                    'id' => $classe->id,
                    'nom' => $classe->nom,
                    'niveau' => $classe->niveau,
                    'nombre_eleves' => $classe->eleves_count,
                    'moyenne_classe' => round($moyenneClasse ?? 0, 2),
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Statistiques par classe',
            'data' => $classes
        ]);
    }

    /**
     * Statistiques des notes
     */
    public function statsNotes()
    {
        $stats = [
            'par_periode' => Note::selectRaw('periode, COUNT(*) as total, AVG(valeur) as moyenne')
                ->groupBy('periode')
                ->get(),
            'par_type_evaluation' => Note::selectRaw('type_evaluation, COUNT(*) as total, AVG(valeur) as moyenne')
                ->groupBy('type_evaluation')
                ->get(),
            'distribution_notes' => [
                'excellent' => Note::where('valeur', '>=', 16)->count(),
                'tres_bien' => Note::whereBetween('valeur', [14, 15.99])->count(),
                'bien' => Note::whereBetween('valeur', [12, 13.99])->count(),
                'assez_bien' => Note::whereBetween('valeur', [10, 11.99])->count(),
                'passable' => Note::whereBetween('valeur', [8, 9.99])->count(),
                'insuffisant' => Note::where('valeur', '<', 8)->count(),
            ]
        ];

        return response()->json([
            'success' => true,
            'message' => 'Statistiques des notes',
            'data' => $stats
        ]);
    }
}