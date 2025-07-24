<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
use App\Models\User;
use App\Models\Note;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class EnseignantController extends Controller
{
    /**
     * Liste des enseignants (Admin)
     */
    public function index(Request $request)
    {
        $query = Enseignant::with(['user', 'matieres']);

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('specialite', 'like', "%{$search}%");
        }

        $enseignants = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Liste des enseignants',
            'data' => $enseignants
        ]);
    }

    /**
     * Créer un enseignant (Admin)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'telephone' => 'required|string',
            'specialite' => 'nullable|string',
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
            'role' => 'enseignant',
        ]);

        // Créer l'enseignant
        $enseignant = Enseignant::create([
            'telephone' => $request->telephone,
            'specialite' => $request->specialite,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Enseignant créé avec succès',
            'data' => $enseignant->load('user')
        ], 201);
    }

    /**
     * Afficher un enseignant (Admin)
     */
    public function show($id)
    {
        $enseignant = Enseignant::with(['user', 'matieres', 'classesPrincipales'])
                                ->find($id);

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Enseignant non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Détails de l\'enseignant',
            'data' => $enseignant
        ]);
    }

    /**
     * Mettre à jour un enseignant (Admin)
     */
    public function update(Request $request, $id)
    {
        $enseignant = Enseignant::with('user')->find($id);

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Enseignant non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|required|string|max:255',
            'prenom' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $enseignant->user_id,
            'password' => 'sometimes|required|string|min:6',
            'telephone' => 'sometimes|required|string',
            'specialite' => 'sometimes|nullable|string',
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
        $enseignant->user->update($userData);

        // Mettre à jour l'enseignant
        $enseignantData = $request->only(['telephone', 'specialite']);
        $enseignant->update($enseignantData);

        return response()->json([
            'success' => true,
            'message' => 'Enseignant mis à jour avec succès',
            'data' => $enseignant->load('user')
        ]);
    }

    /**
     * Supprimer un enseignant (Admin)
     */
    public function destroy($id)
    {
        $enseignant = Enseignant::find($id);

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Enseignant non trouvé'
            ], 404);
        }

        $enseignant->delete(); // Cascade delete grâce aux migrations

        return response()->json([
            'success' => true,
            'message' => 'Enseignant supprimé avec succès'
        ]);
    }

    /**
     * Dashboard enseignant
     */
    public function dashboard()
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        $stats = [
            'enseignant' => $enseignant->load('user'),
            'nombre_matieres' => $enseignant->matieres()->count(),
            'nombre_classes' => $enseignant->getClassesEnseignees()->count(),
            'nombre_eleves' => $enseignant->getElevesEnseignes()->count(),
            'notes_ajoutees_semaine' => Note::whereHas('matiere', function($q) use ($enseignant) {
                $q->where('enseignant_id', $enseignant->id);
            })->where('created_at', '>=', now()->subWeek())->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Dashboard enseignant',
            'data' => $stats
        ]);
    }

    /**
     * Mes classes
     */
    public function mesClasses()
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        $classes = $enseignant->getClassesEnseignees();

        return response()->json([
            'success' => true,
            'message' => 'Mes classes',
            'data' => $classes
        ]);
    }

    /**
     * Mes matières
     */
    public function mesMatieres()
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        $matieres = $enseignant->matieres()->get();

        return response()->json([
            'success' => true,
            'message' => 'Mes matières',
            'data' => $matieres
        ]);
    }

    /**
     * Mes élèves
     */
    public function mesEleves()
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        $eleves = $enseignant->getElevesEnseignes();

        return response()->json([
            'success' => true,
            'message' => 'Mes élèves',
            'data' => $eleves
        ]);
    }

    /**
     * Mes notes
     */
    public function mesNotes(Request $request)
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        $query = Note::whereHas('matiere', function($q) use ($enseignant) {
            $q->where('enseignant_id', $enseignant->id);
        })->with(['eleve.user', 'matiere', 'classe']);

        // Filtrage par période
        if ($request->has('periode')) {
            $query->where('periode', $request->periode);
        }

        // Filtrage par matière
        if ($request->has('matiere_id')) {
            $query->where('matiere_id', $request->matiere_id);
        }

        // Filtrage par classe
        if ($request->has('classe_id')) {
            $query->where('classe_id', $request->classe_id);
        }

        $notes = $query->orderBy('created_at', 'desc')
                      ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Mes notes',
            'data' => $notes
        ]);
    }

    /**
     * Ajouter une note
     */
    public function ajouterNote(Request $request)
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'valeur' => 'required|numeric|min:0|max:20',
            'periode' => 'required|in:trimestre_1,trimestre_2,trimestre_3',
            'date_note' => 'required|date',
            'type_evaluation' => 'required|string',
            'commentaire' => 'nullable|string',
            'eleve_id' => 'required|exists:eleves,id',
            'matiere_id' => 'required|exists:matieres,id',
            'classe_id' => 'required|exists:classes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier que la matière appartient à cet enseignant
        $matiere = $enseignant->matieres()->find($request->matiere_id);
        if (!$matiere) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à noter cette matière'
            ], 403);
        }

        $note = Note::create($request->all());

        // Créer une notification pour l'élève
        $eleve = $note->eleve;
        Notification::creerNotification(
            $eleve->user_id,
            'Nouvelle note ajoutée',
            'Une nouvelle note (' . $note->valeur . '/20) a été ajoutée en ' . $matiere->nom . '.',
            'note',
            'normale',
            $user->id,
            [
                'valeur' => $note->valeur,
                'matiere' => $matiere->nom,
                'note_id' => $note->id
            ],
            '/notes/' . $note->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Note ajoutée avec succès',
            'data' => $note->load(['eleve.user', 'matiere', 'classe'])
        ], 201);
    }

    /**
     * Modifier une note
     */
    public function modifierNote(Request $request, $id)
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        $note = Note::whereHas('matiere', function($q) use ($enseignant) {
            $q->where('enseignant_id', $enseignant->id);
        })->find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note non trouvée ou non autorisée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'valeur' => 'sometimes|required|numeric|min:0|max:20',
            'periode' => 'sometimes|required|in:trimestre_1,trimestre_2,trimestre_3',
            'date_note' => 'sometimes|required|date',
            'type_evaluation' => 'sometimes|required|string',
            'commentaire' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $note->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Note modifiée avec succès',
            'data' => $note->load(['eleve.user', 'matiere', 'classe'])
        ]);
    }

    /**
     * Supprimer une note
     */
    public function supprimerNote($id)
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        $note = Note::whereHas('matiere', function($q) use ($enseignant) {
            $q->where('enseignant_id', $enseignant->id);
        })->find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note non trouvée ou non autorisée'
            ], 404);
        }

        $note->delete();

        return response()->json([
            'success' => true,
            'message' => 'Note supprimée avec succès'
        ]);
    }

    /**
     * Notes d'une classe
     */
    public function notesClasse($classeId)
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        $notes = Note::where('classe_id', $classeId)
                    ->whereHas('matiere', function($q) use ($enseignant) {
                        $q->where('enseignant_id', $enseignant->id);
                    })
                    ->with(['eleve.user', 'matiere'])
                    ->orderBy('date_note', 'desc')
                    ->get();

        return response()->json([
            'success' => true,
            'message' => 'Notes de la classe',
            'data' => $notes
        ]);
    }

    /**
     * Notes d'une matière
     */
    public function notesMatiere($matiereId)
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        // Vérifier que la matière appartient à cet enseignant
        $matiere = $enseignant->matieres()->find($matiereId);
        if (!$matiere) {
            return response()->json([
                'success' => false,
                'message' => 'Matière non autorisée'
            ], 403);
        }

        $notes = Note::where('matiere_id', $matiereId)
                    ->with(['eleve.user', 'classe'])
                    ->orderBy('date_note', 'desc')
                    ->get();

        return response()->json([
            'success' => true,
            'message' => 'Notes de la matière',
            'data' => [
                'matiere' => $matiere,
                'notes' => $notes
            ]
        ]);
    }

    /**
     * Bulletins des classes
     */
    public function bulletinsClasses()
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        $classes = $enseignant->getClassesEnseignees();
        $bulletins = collect();

        foreach ($classes as $classe) {
            $classBulletins = $classe->bulletins()->with('eleve.user')->get();
            $bulletins = $bulletins->merge($classBulletins);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulletins des classes',
            'data' => $bulletins->sortByDesc('created_at')->values()
        ]);
    }

    /**
     * Profil enseignant
     */
    public function profile()
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil enseignant',
            'data' => $enseignant->load(['user', 'matieres', 'classesPrincipales'])
        ]);
    }
}