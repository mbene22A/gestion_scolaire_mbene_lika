<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Eleve;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    /**
     * Liste des documents
     */
    public function index(Request $request)
    {
        $query = Document::with(['eleve.user', 'uploadedBy']);

        // Filtrage par élève
        if ($request->has('eleve_id')) {
            $query->where('eleve_id', $request->eleve_id);
        }

        // Filtrage par type de document
        if ($request->has('type_document')) {
            $query->where('type_document', $request->type_document);
        }

        // Filtrage par statut obligatoire
        if ($request->has('obligatoire')) {
            $query->where('obligatoire', $request->boolean('obligatoire'));
        }

        // Filtrage par statut validé
        if ($request->has('valide')) {
            $query->where('valide', $request->boolean('valide'));
        }

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nom_original', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('eleve.user', function($qu) use ($search) {
                      $qu->where('nom', 'like', "%{$search}%")
                         ->orWhere('prenom', 'like', "%{$search}%");
                  });
            });
        }

        $documents = $query->orderBy('created_at', 'desc')
                          ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Liste des documents',
            'data' => $documents
        ]);
    }

    /**
     * Uploader un document
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fichier' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
            'type_document' => 'required|in:certificat_scolarite,bulletin,justificatif,autre',
            'obligatoire' => 'boolean',
            'description' => 'nullable|string|max:500',
            'eleve_id' => 'required|exists:eleves,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('fichier');
        $eleve = Eleve::find($request->eleve_id);

        // Générer un nom unique pour le fichier
        $nomFichier = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $cheminFichier = 'documents/' . $eleve->matricule_eleve . '/' . $nomFichier;

        // Stocker le fichier
        $path = $file->storeAs('documents/' . $eleve->matricule_eleve, $nomFichier, 'public');

        if (!$path) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload du fichier'
            ], 500);
        }

        $document = Document::create([
            'nom_fichier' => $nomFichier,
            'nom_original' => $file->getClientOriginalName(),
            'chemin_fichier' => $cheminFichier,
            'type_fichier' => $file->getClientOriginalExtension(),
            'mime_type' => $file->getMimeType(),
            'taille_fichier' => $file->getSize(),
            'type_document' => $request->type_document,
            'obligatoire' => $request->boolean('obligatoire', false),
            'description' => $request->description,
            'eleve_id' => $request->eleve_id,
            'uploaded_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploadé avec succès',
            'data' => $document->load(['eleve.user', 'uploadedBy'])
        ], 201);
    }

    /**
     * Afficher un document
     */
    public function show($id)
    {
        $document = Document::with(['eleve.user', 'uploadedBy'])->find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Détails du document',
            'data' => $document
        ]);
    }

    /**
     * Mettre à jour un document
     */
    public function update(Request $request, $id)
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'type_document' => 'sometimes|required|in:certificat_scolarite,bulletin,justificatif,autre',
            'obligatoire' => 'sometimes|boolean',
            'valide' => 'sometimes|boolean',
            'description' => 'sometimes|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $document->update($request->only([
            'type_document', 'obligatoire', 'valide', 'description'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Document mis à jour avec succès',
            'data' => $document->load(['eleve.user', 'uploadedBy'])
        ]);
    }

    /**
     * Supprimer un document
     */
    public function destroy($id)
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document non trouvé'
            ], 404);
        }

        // Supprimer le fichier physique
        if (Storage::disk('public')->exists($document->chemin_fichier)) {
            Storage::disk('public')->delete($document->chemin_fichier);
        }

        $document->delete();

        return response()->json([
            'success' => true,
            'message' => 'Document supprimé avec succès'
        ]);
    }

    /**
     * Valider un document
     */
    public function validate($id)
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document non trouvé'
            ], 404);
        }

        $document->update(['valide' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Document validé avec succès',
            'data' => $document->load(['eleve.user', 'uploadedBy'])
        ]);
    }

    /**
     * Télécharger un document
     */
    public function download($id)
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document non trouvé'
            ], 404);
        }

        // Vérifier les permissions
        $user = auth()->user();
        
        // Admin peut tout télécharger
        if (!$user->isAdmin()) {
            // Enseignant peut télécharger les documents de ses élèves
            if ($user->isEnseignant()) {
                $enseignant = $user->enseignant;
                $elevesEnseignes = $enseignant->getElevesEnseignes()->pluck('id');
                
                if (!$elevesEnseignes->contains($document->eleve_id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Non autorisé à télécharger ce document'
                    ], 403);
                }
            }
            // Élève peut télécharger uniquement ses documents
            elseif ($user->isEleve()) {
                if ($user->eleve->id !== $document->eleve_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Non autorisé à télécharger ce document'
                    ], 403);
                }
            }
        }

        $cheminComplet = storage_path('app/public/' . $document->chemin_fichier);

        if (!file_exists($cheminComplet)) {
            return response()->json([
                'success' => false,
                'message' => 'Fichier non trouvé sur le serveur'
            ], 404);
        }

        return response()->download($cheminComplet, $document->nom_original);
    }

    /**
     * Documents d'un élève (pour l'espace élève)
     */
    public function mesDocuments()
    {
        $user = auth()->user();
        $eleve = $user->eleve;

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Profil élève non trouvé'
            ], 404);
        }

        $documents = $eleve->documents()
                          ->with('uploadedBy')
                          ->orderBy('created_at', 'desc')
                          ->get();

        return response()->json([
            'success' => true,
            'message' => 'Mes documents',
            'data' => $documents
        ]);
    }

    /**
     * Upload de document par un élève
     */
    public function uploadParEleve(Request $request)
    {
        $user = auth()->user();
        $eleve = $user->eleve;

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Profil élève non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'fichier' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png',
            'type_document' => 'required|in:certificat_scolarite,justificatif,autre',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('fichier');

        // Générer un nom unique pour le fichier
        $nomFichier = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $cheminFichier = 'documents/' . $eleve->matricule_eleve . '/' . $nomFichier;

        // Stocker le fichier
        $path = $file->storeAs('documents/' . $eleve->matricule_eleve, $nomFichier, 'public');

        if (!$path) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload du fichier'
            ], 500);
        }

        $document = Document::create([
            'nom_fichier' => $nomFichier,
            'nom_original' => $file->getClientOriginalName(),
            'chemin_fichier' => $cheminFichier,
            'type_fichier' => $file->getClientOriginalExtension(),
            'mime_type' => $file->getMimeType(),
            'taille_fichier' => $file->getSize(),
            'type_document' => $request->type_document,
            'obligatoire' => false,
            'valide' => false, // Nécessite validation par admin
            'description' => $request->description,
            'eleve_id' => $eleve->id,
            'uploaded_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploadé avec succès. En attente de validation.',
            'data' => $document
        ], 201);
    }

    /**
     * Statistiques des documents
     */
    public function statistiques()
    {
        $stats = [
            'total_documents' => Document::count(),
            'documents_valides' => Document::where('valide', true)->count(),
            'documents_en_attente' => Document::where('valide', false)->count(),
            'documents_obligatoires' => Document::where('obligatoire', true)->count(),
            'par_type' => Document::groupBy('type_document')
                                 ->selectRaw('type_document, count(*) as total')
                                 ->pluck('total', 'type_document'),
            'taille_totale' => Document::sum('taille_fichier'),
            'documents_recents' => Document::where('created_at', '>=', now()->subWeek())->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Statistiques des documents',
            'data' => $stats
        ]);
    }

    /**
     * Documents en attente de validation
     */
    public function enAttenteValidation()
    {
        $documents = Document::where('valide', false)
                            ->with(['eleve.user', 'uploadedBy'])
                            ->orderBy('created_at', 'desc')
                            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Documents en attente de validation',
            'data' => $documents
        ]);
    }

    /**
     * Valider plusieurs documents en masse
     */
    public function validerEnMasse(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_ids' => 'required|array|min:1',
            'document_ids.*' => 'exists:documents,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $updated = Document::whereIn('id', $request->document_ids)
                          ->update(['valide' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Documents validés en masse',
            'data' => ['documents_valides' => $updated]
        ]);
    }

    /**
     * Nettoyage des anciens documents
     */
    public function nettoyage(Request $request)
    {
        $mois = $request->get('mois', 12);
        
        $documentsAnciens = Document::where('created_at', '<', now()->subMonths($mois))
                                  ->where('obligatoire', false)
                                  ->get();

        $supprimesCount = 0;
        foreach ($documentsAnciens as $document) {
            // Supprimer le fichier physique
            if (Storage::disk('public')->exists($document->chemin_fichier)) {
                Storage::disk('public')->delete($document->chemin_fichier);
            }
            $document->delete();
            $supprimesCount++;
        }

        return response()->json([
            'success' => true,
            'message' => 'Nettoyage des anciens documents terminé',
            'data' => ['documents_supprimes' => $supprimesCount]
        ]);
    }
}