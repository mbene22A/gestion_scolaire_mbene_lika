<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NoteController extends Controller
{
    /**
     * Liste des notes (Admin)
     */
    public function index(Request $request)
    {
        $query = Note::with(['eleve.user', 'matiere.enseignant.user', 'classe']);

        // Filtrage par période
        if ($request->has('periode')) {
            $query->where('periode', $request->periode);
        }

        // Filtrage par classe
        if ($request->has('classe_id')) {
            $query->where('classe_id', $request->classe_id);
        }

        // Filtrage par matière
        if ($request->has('matiere_id')) {
            $query->where('matiere_id', $request->matiere_id);
        }

        // Filtrage par élève
        if ($request->has('eleve_id')) {
            $query->where('eleve_id', $request->eleve_id);
        }

        // Filtrage par type d'évaluation
        if ($request->has('type_evaluation')) {
            $query->where('type_evaluation', $request->type_evaluation);
        }

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('eleve.user', function($qu) use ($search) {
                    $qu->where('nom', 'like', "%{$search}%")
                       ->orWhere('prenom', 'like', "%{$search}%");
                })->orWhereHas('matiere', function($qu) use ($search) {
                    $qu->where('nom', 'like', "%{$search}%")
                       ->orWhere('code', 'like', "%{$search}%");
                });
            });
        }

        $notes = $query->orderBy('date_note', 'desc')
                      ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Liste des notes',
            'data' => $notes
        ]);
    }

    /**
     * Créer une note (Admin)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'valeur' => 'required|numeric|min:0|max:20',
            'periode' => 'required|in:trimestre_1,trimestre_2,trimestre_3',
            'date_note' => 'required|date',
            'type_evaluation' => 'required|string|max:255',
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

        $note = Note::create($request->all());

        // Créer une notification pour l'élève
        $eleve = $note->eleve;
        $matiere = $note->matiere;
        Notification::creerNotification(
            $eleve->user_id,
            'Nouvelle note ajoutée',
            'Une nouvelle note (' . $note->valeur . '/20) a été ajoutée en ' . $matiere->nom . '.',
            'note',
            'normale',
            auth()->id(),
            [
                'valeur' => $note->valeur,
                'matiere' => $matiere->nom,
                'note_id' => $note->id
            ],
            '/notes/' . $note->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Note créée avec succès',
            'data' => $note->load(['eleve.user', 'matiere', 'classe'])
        ], 201);
    }

    /**
     * Afficher une note
     */
    public function show($id)
    {
        $note = Note::with(['eleve.user', 'matiere.enseignant.user', 'classe'])
                    ->find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Détails de la note',
            'data' => $note
        ]);
    }

    /**
     * Mettre à jour une note (Admin)
     */
    public function update(Request $request, $id)
    {
        $note = Note::find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'valeur' => 'sometimes|required|numeric|min:0|max:20',
            'periode' => 'sometimes|required|in:trimestre_1,trimestre_2,trimestre_3',
            'date_note' => 'sometimes|required|date',
            'type_evaluation' => 'sometimes|required|string|max:255',
            'commentaire' => 'sometimes|nullable|string',
            'eleve_id' => 'sometimes|required|exists:eleves,id',
            'matiere_id' => 'sometimes|required|exists:matieres,id',
            'classe_id' => 'sometimes|required|exists:classes,id',
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
            'message' => 'Note mise à jour avec succès',
            'data' => $note->load(['eleve.user', 'matiere', 'classe'])
        ]);
    }

    /**
     * Supprimer une note (Admin)
     */
    public function destroy($id)
    {
        $note = Note::find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note non trouvée'
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
    public function notesClasse($classeId, Request $request)
    {
        $query = Note::where('classe_id', $classeId)
                    ->with(['eleve.user', 'matiere']);

        // Filtrage par période
        if ($request->has('periode')) {
            $query->where('periode', $request->periode);
        }

        // Filtrage par matière
        if ($request->has('matiere_id')) {
            $query->where('matiere_id', $request->matiere_id);
        }

        $notes = $query->orderBy('date_note', 'desc')->get();

        // Statistiques de la classe
        $stats = [
            'nombre_notes' => $notes->count(),
            'moyenne_classe' => round($notes->avg('valeur') ?? 0, 2),
            'meilleure_note' => $notes->max('valeur') ?? 0,
            'moins_bonne_note' => $notes->min('valeur') ?? 0,
            'notes_par_matiere' => $notes->groupBy('matiere.nom')->map(function($notesMatiere) {
                return [
                    'nombre' => $notesMatiere->count(),
                    'moyenne' => round($notesMatiere->avg('valeur'), 2)
                ];
            })
        ];

        return response()->json([
            'success' => true,
            'message' => 'Notes de la classe',
            'data' => [
                'notes' => $notes,
                'statistiques' => $stats
            ]
        ]);
    }

    /**
     * Notes par période
     */
    public function notesPeriode($periode, Request $request)
    {
        $query = Note::where('periode', $periode)
                    ->with(['eleve.user', 'matiere', 'classe']);

        // Filtrage par classe
        if ($request->has('classe_id')) {
            $query->where('classe_id', $request->classe_id);
        }

        // Filtrage par matière
        if ($request->has('matiere_id')) {
            $query->where('matiere_id', $request->matiere_id);
        }

        $notes = $query->orderBy('date_note', 'desc')->get();

        // Statistiques de la période
        $stats = [
            'periode' => $periode,
            'nombre_notes' => $notes->count(),
            'moyenne_generale' => round($notes->avg('valeur') ?? 0, 2),
            'distribution' => [
                'excellent' => $notes->where('valeur', '>=', 16)->count(),
                'tres_bien' => $notes->whereBetween('valeur', [14, 15.99])->count(),
                'bien' => $notes->whereBetween('valeur', [12, 13.99])->count(),
                'assez_bien' => $notes->whereBetween('valeur', [10, 11.99])->count(),
                'passable' => $notes->whereBetween('valeur', [8, 9.99])->count(),
                'insuffisant' => $notes->where('valeur', '<', 8)->count(),
            ],
            'notes_par_classe' => $notes->groupBy('classe.nom')->map(function($notesClasse) {
                return [
                    'nombre' => $notesClasse->count(),
                    'moyenne' => round($notesClasse->avg('valeur'), 2)
                ];
            })
        ];

        return response()->json([
            'success' => true,
            'message' => 'Notes de la période',
            'data' => [
                'notes' => $notes,
                'statistiques' => $stats
            ]
        ]);
    }
}