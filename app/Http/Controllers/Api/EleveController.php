<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Models\User;
use App\Models\Classe;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class EleveController extends Controller
{
    /**
     * Liste des élèves (Admin)
     */
    public function index(Request $request)
    {
        $query = Eleve::with(['user', 'classe']);

        // Filtrage par classe
        if ($request->has('classe_id')) {
            $query->where('classe_id', $request->classe_id);
        }

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('matricule_eleve', 'like', "%{$search}%");
        }

        $eleves = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Liste des élèves',
            'data' => $eleves
        ]);
    }

    /**
     * Créer un élève (Admin)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'matricule_eleve' => 'required|string|unique:eleves',
            'date_naissance' => 'required|date',
            'adresse' => 'required|string',
            'telephone_parent' => 'required|string',
            'email_parent' => 'required|email',
            'classe_id' => 'required|exists:classes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Créer l'utilisateur
        $user = User::create([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'eleve',
        ]);

        // Créer l'élève
        $eleve = Eleve::create([
            'matricule_eleve' => $request->matricule_eleve,
            'date_naissance' => $request->date_naissance,
            'adresse' => $request->adresse,
            'telephone_parent' => $request->telephone_parent,
            'email_parent' => $request->email_parent,
            'classe_id' => $request->classe_id,
            'user_id' => $user->id,
        ]);

        // Créer une notification de bienvenue
        Notification::creerNotification(
            $user->id,
            'Bienvenue !',
            'Votre compte a été créé avec succès. Votre numéro d\'étudiant est : ' . $request->matricule_eleve,
            'inscription',
            'haute',
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Élève créé avec succès',
            'data' => $eleve->load(['user', 'classe'])
        ], 201);
    }

    /**
     * Afficher un élève (Admin)
     */
    public function show($id)
    {
        $eleve = Eleve::with(['user', 'classe', 'notes.matiere', 'bulletins', 'documents'])
                      ->find($id);

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Élève non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Détails de l\'élève',
            'data' => $eleve
        ]);
    }

    /**
     * Mettre à jour un élève (Admin)
     */
    public function update(Request $request, $id)
    {
        $eleve = Eleve::with('user')->find($id);

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Élève non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|required|string|max:255',
            'prenom' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $eleve->user_id,
            'password' => 'sometimes|required|string|min:6',
            'matricule_eleve' => 'sometimes|required|string|unique:eleves,matricule_eleve,' . $id,
            'date_naissance' => 'sometimes|required|date',
            'adresse' => 'sometimes|required|string',
            'telephone_parent' => 'sometimes|required|string',
            'email_parent' => 'sometimes|required|email',
            'classe_id' => 'sometimes|required|exists:classes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Mettre à jour l'utilisateur
        $userData = $request->only(['nom', 'prenom', 'email']);
        if ($request->has('password')) {
            $userData['password'] = Hash::make($request->password);
        }
        $eleve->user->update($userData);

        // Mettre à jour l'élève
        $eleveData = $request->only([
            'matricule_eleve', 'date_naissance', 'adresse', 
            'telephone_parent', 'email_parent', 'classe_id'
        ]);
        $eleve->update($eleveData);

        return response()->json([
            'success' => true,
            'message' => 'Élève mis à jour avec succès',
            'data' => $eleve->load(['user', 'classe'])
        ]);
    }

    /**
     * Supprimer un élève (Admin)
     */
    public function destroy($id)
    {
        $eleve = Eleve::find($id);

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Élève non trouvé'
            ], 404);
        }

        $eleve->delete(); // Cascade delete grâce aux migrations

        return response()->json([
            'success' => true,
            'message' => 'Élève supprimé avec succès'
        ]);
    }

    /**
     * Dashboard élève
     */
    public function dashboard()
    {
        $user = auth()->user();
        $eleve = $user->eleve;

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Profil élève non trouvé'
            ], 404);
        }

        $stats = [
            'eleve' => $eleve->load(['classe', 'user']),
            'derniere_moyenne' => $eleve->getMoyenneGenerale(),
            'notes_recentes' => $eleve->notes()->with('matiere')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
            'bulletins_disponibles' => $eleve->bulletins()
                ->where('publie', true)
                ->count(),
            'notifications_non_lues' => $user->notifications()
                ->where('lu', false)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Dashboard élève',
            'data' => $stats
        ]);
    }

    /**
     * Profil élève
     */
    public function profile()
    {
        $user = auth()->user();
        $eleve = $user->eleve;

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Profil élève non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil élève',
            'data' => $eleve->load(['user', 'classe'])
        ]);
    }

    /**
     * Ma classe
     */
    public function maClasse()
    {
        $user = auth()->user();
        $eleve = $user->eleve;

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Profil élève non trouvé'
            ], 404);
        }

        $classe = $eleve->classe->load(['enseignantPrincipal', 'eleves.user']);

        return response()->json([
            'success' => true,
            'message' => 'Ma classe',
            'data' => $classe
        ]);
    }

    /**
     * Mes notes
     */
    public function mesNotes(Request $request)
    {
        $user = auth()->user();
        $eleve = $user->eleve;

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Profil élève non trouvé'
            ], 404);
        }

        $query = $eleve->notes()->with(['matiere', 'classe']);

        // Filtrage par période
        if ($request->has('periode')) {
            $query->where('periode', $request->periode);
        }

        // Filtrage par matière
        if ($request->has('matiere_id')) {
            $query->where('matiere_id', $request->matiere_id);
        }

        $notes = $query->orderBy('date_note', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Mes notes',
            'data' => $notes
        ]);
    }

    /**
     * Notes par période
     */
    public function notesPeriode($periode)
    {
        $user = auth()->user();
        $eleve = $user->eleve;

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Profil élève non trouvé'
            ], 404);
        }

        $notes = $eleve->getNotesParMatiere($periode);
        $moyenne = $eleve->getMoyenneGenerale($periode);

        return response()->json([
            'success' => true,
            'message' => 'Notes de la période',
            'data' => [
                'periode' => $periode,
                'notes_par_matiere' => $notes,
                'moyenne_generale' => round($moyenne ?? 0, 2)
            ]
        ]);
    }

    /**
     * Mes bulletins
     */
    public function mesBulletins()
    {
        $user = auth()->user();
        $eleve = $user->eleve;

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Profil élève non trouvé'
            ], 404);
        }

        $bulletins = $eleve->bulletins()
                          ->where('publie', true)
                          ->orderBy('created_at', 'desc')
                          ->get();

        return response()->json([
            'success' => true,
            'message' => 'Mes bulletins',
            'data' => $bulletins
        ]);
    }

    /**
     * Détails d'un bulletin
     */
    public function bulletinDetails($id)
    {
        $user = auth()->user();
        $eleve = $user->eleve;

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Profil élève non trouvé'
            ], 404);
        }

        $bulletin = $eleve->bulletins()
                         ->where('id', $id)
                         ->where('publie', true)
                         ->first();

        if (!$bulletin) {
            return response()->json([
                'success' => false,
                'message' => 'Bulletin non trouvé ou non publié'
            ], 404);
        }

        $notesDetaillees = $bulletin->getNotesDetaillees();

        return response()->json([
            'success' => true,
            'message' => 'Détails du bulletin',
            'data' => [
                'bulletin' => $bulletin,
                'notes_detaillees' => $notesDetaillees
            ]
        ]);
    }

    /**
     * Mes notifications
     */
    public function mesNotifications(Request $request)
    {
        $user = auth()->user();

        $query = $user->notifications();

        // Filtrage par statut lu/non lu
        if ($request->has('lu')) {
            $query->where('lu', $request->boolean('lu'));
        }

        $notifications = $query->orderBy('created_at', 'desc')
                              ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Mes notifications',
            'data' => $notifications
        ]);
    }

    /**
     * Marquer une notification comme lue
     */
    public function marquerCommeLu($id)
    {
        $user = auth()->user();
        $notification = $user->notifications()->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification non trouvée'
            ], 404);
        }

        $notification->marquerCommeLue();

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue'
        ]);
    }

    /**
     * Compter les notifications non lues
     */
    public function countUnread()
    {
        $user = auth()->user();
        $count = $user->notifications()->where('lu', false)->count();

        return response()->json([
            'success' => true,
            'data' => ['count' => $count]
        ]);
    }
}