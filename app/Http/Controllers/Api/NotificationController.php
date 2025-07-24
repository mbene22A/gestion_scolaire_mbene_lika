<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * Liste des notifications
     */
    public function index(Request $request)
    {
        $query = Notification::with(['user', 'envoyePar']);

        // Filtrage par utilisateur
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filtrage par type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filtrage par priorité
        if ($request->has('priorite')) {
            $query->where('priorite', $request->priorite);
        }

        // Filtrage par statut lu/non lu
        if ($request->has('lu')) {
            $query->where('lu', $request->boolean('lu'));
        }

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $notifications = $query->orderBy('created_at', 'desc')
                              ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Liste des notifications',
            'data' => $notifications
        ]);
    }

    /**
     * Créer une notification
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titre' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:info,bulletin,document,urgent,inscription,note',
            'priorite' => 'required|in:basse,normale,haute,urgente',
            'donnees_supplementaires' => 'nullable|array',
            'lien_action' => 'nullable|string|max:255',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $notification = Notification::create([
            'titre' => $request->titre,
            'message' => $request->message,
            'type' => $request->type,
            'priorite' => $request->priorite,
            'donnees_supplementaires' => $request->donnees_supplementaires,
            'lien_action' => $request->lien_action,
            'user_id' => $request->user_id,
            'envoye_par' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification créée avec succès',
            'data' => $notification->load(['user', 'envoyePar'])
        ], 201);
    }

    /**
     * Afficher une notification
     */
    public function show($id)
    {
        $notification = Notification::with(['user', 'envoyePar'])->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Détails de la notification',
            'data' => $notification
        ]);
    }

    /**
     * Mettre à jour une notification
     */
    public function update(Request $request, $id)
    {
        $notification = Notification::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'titre' => 'sometimes|required|string|max:255',
            'message' => 'sometimes|required|string',
            'type' => 'sometimes|required|in:info,bulletin,document,urgent,inscription,note',
            'priorite' => 'sometimes|required|in:basse,normale,haute,urgente',
            'donnees_supplementaires' => 'sometimes|nullable|array',
            'lien_action' => 'sometimes|nullable|string|max:255',
            'lu' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only([
            'titre', 'message', 'type', 'priorite', 
            'donnees_supplementaires', 'lien_action', 'lu'
        ]);

        // Si on marque comme lu, ajouter la date de lecture
        if ($request->has('lu') && $request->lu) {
            $data['date_lecture'] = now();
        }

        $notification->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Notification mise à jour avec succès',
            'data' => $notification->load(['user', 'envoyePar'])
        ]);
    }

    /**
     * Supprimer une notification
     */
    public function destroy($id)
    {
        $notification = Notification::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification non trouvée'
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification supprimée avec succès'
        ]);
    }

    /**
     * Envoyer des notifications en masse
     */
    public function sendBulk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titre' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:info,bulletin,document,urgent,inscription,note',
            'priorite' => 'required|in:basse,normale,haute,urgente',
            'donnees_supplementaires' => 'nullable|array',
            'lien_action' => 'nullable|string|max:255',
            'destinataires' => 'required|array|min:1',
            'destinataires.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $notificationsCreees = [];
        $erreurs = [];

        foreach ($request->destinataires as $userId) {
            try {
                $notification = Notification::create([
                    'titre' => $request->titre,
                    'message' => $request->message,
                    'type' => $request->type,
                    'priorite' => $request->priorite,
                    'donnees_supplementaires' => $request->donnees_supplementaires,
                    'lien_action' => $request->lien_action,
                    'user_id' => $userId,
                    'envoye_par' => auth()->id(),
                ]);

                $notificationsCreees[] = $notification;

            } catch (\Exception $e) {
                $erreurs[] = "Erreur pour l'utilisateur $userId: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifications envoyées en masse',
            'data' => [
                'notifications_creees' => count($notificationsCreees),
                'erreurs' => $erreurs,
                'notifications' => $notificationsCreees
            ]
        ]);
    }

    /**
     * Compter les notifications non lues
     */
    public function countUnread(Request $request)
    {
        $userId = $request->get('user_id', auth()->id());
        
        $count = Notification::where('user_id', $userId)
                            ->where('lu', false)
                            ->count();

        return response()->json([
            'success' => true,
            'data' => ['count' => $count]
        ]);
    }

    /**
     * Notifications récentes
     */
    public function recent(Request $request)
    {
        $limit = $request->get('limit', 10);
        $userId = $request->get('user_id', auth()->id());

        $notifications = Notification::where('user_id', $userId)
                                   ->with('envoyePar')
                                   ->orderBy('created_at', 'desc')
                                   ->limit($limit)
                                   ->get();

        return response()->json([
            'success' => true,
            'message' => 'Notifications récentes',
            'data' => $notifications
        ]);
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllAsRead(Request $request)
    {
        $userId = $request->get('user_id', auth()->id());

        $updated = Notification::where('user_id', $userId)
                              ->where('lu', false)
                              ->update([
                                  'lu' => true,
                                  'date_lecture' => now()
                              ]);

        return response()->json([
            'success' => true,
            'message' => 'Toutes les notifications ont été marquées comme lues',
            'data' => ['updated_count' => $updated]
        ]);
    }

    /**
     * Statistiques des notifications
     */
    public function stats(Request $request)
    {
        $userId = $request->get('user_id');

        $query = Notification::query();
        
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $stats = [
            'total' => $query->count(),
            'non_lues' => $query->where('lu', false)->count(),
            'lues' => $query->where('lu', true)->count(),
            'par_type' => $query->groupBy('type')
                               ->selectRaw('type, count(*) as total')
                               ->pluck('total', 'type'),
            'par_priorite' => $query->groupBy('priorite')
                                  ->selectRaw('priorite, count(*) as total')
                                  ->pluck('total', 'priorite'),
            'urgentes_non_lues' => $query->where('priorite', 'urgente')
                                        ->where('lu', false)
                                        ->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Statistiques des notifications',
            'data' => $stats
        ]);
    }

    /**
     * Supprimer les notifications lues anciennes
     */
    public function cleanupOld(Request $request)
    {
        $days = $request->get('days', 30);
        
        $deleted = Notification::where('lu', true)
                              ->where('date_lecture', '<', now()->subDays($days))
                              ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Nettoyage des anciennes notifications terminé',
            'data' => ['deleted_count' => $deleted]
        ]);
    }

    /**
     * Envoyer une notification à tous les utilisateurs d'un rôle
     */
    public function sendToRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titre' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:info,bulletin,document,urgent,inscription,note',
            'priorite' => 'required|in:basse,normale,haute,urgente',
            'role' => 'required|in:administrateur,enseignant,eleve',
            'donnees_supplementaires' => 'nullable|array',
            'lien_action' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $users = User::where('role', $request->role)->get();
        $notificationsCreees = [];

        foreach ($users as $user) {
            $notification = Notification::create([
                'titre' => $request->titre,
                'message' => $request->message,
                'type' => $request->type,
                'priorite' => $request->priorite,
                'donnees_supplementaires' => $request->donnees_supplementaires,
                'lien_action' => $request->lien_action,
                'user_id' => $user->id,
                'envoye_par' => auth()->id(),
            ]);

            $notificationsCreees[] = $notification;
        }

        return response()->json([
            'success' => true,
            'message' => "Notifications envoyées à tous les {$request->role}s",
            'data' => [
                'notifications_creees' => count($notificationsCreees),
                'utilisateurs_touches' => $users->count()
            ]
        ]);
    }
}