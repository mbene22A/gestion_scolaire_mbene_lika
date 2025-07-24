<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\EleveController;
use App\Http\Controllers\Api\EnseignantController;
use App\Http\Controllers\Api\ClasseController;
use App\Http\Controllers\Api\MatiereController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\BulletinController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\DocumentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - École Management System
|--------------------------------------------------------------------------
*/

// Route de test (public)
Route::get('test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API École fonctionne correctement',
        'timestamp' => now(),
        'version' => '1.0',
        'laravel' => app()->version(),
        'status' => 'online',
        'endpoints' => [
            'auth' => '/api/auth/*',
            'admin' => '/api/admin/*',
            'enseignant' => '/api/enseignant/*',
            'eleve' => '/api/eleve/*',
        ]
    ]);
});

// Routes d'authentification
Route::prefix('auth')->group(function () {
    // Routes publiques
    Route::post('login', [AuthController::class, 'login']);
    
    // Route register publique (uniquement si aucun admin n'existe)
    Route::post('register-first-admin', function(Request $request) {
        // Vérifier s'il existe déjà un admin
        $adminExists = \App\Models\User::where('role', 'administrateur')->exists();
        
        if ($adminExists) {
            return response()->json([
                'success' => false,
                'message' => 'Un administrateur existe déjà. Contactez l\'admin pour créer des comptes.'
            ], 403);
        }
        
        // Forcer le rôle administrateur
        $request->merge(['role' => 'administrateur']);
        
        return app(AuthController::class)->register($request);
    });
    
    // Route register (protégée - admin seulement)
    Route::middleware(['auth:api', 'role:administrateur'])->group(function () {
        Route::post('register', [AuthController::class, 'register']);
    });
    
    // Routes protégées par JWT
    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('profile', [AuthController::class, 'profile']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
    });
});

// Routes protégées par authentification JWT
Route::middleware('auth:api')->group(function () {
    
    // ========================================
    // ROUTES ADMIN UNIQUEMENT
    // ========================================
    Route::middleware('role:administrateur')->prefix('admin')->group(function () {
        
        // Dashboard Admin
        Route::get('dashboard', [AdminController::class, 'dashboard']);
        Route::get('stats/overview', [AdminController::class, 'statsOverview']);
        Route::get('stats/classes', [AdminController::class, 'statsClasses']);
        Route::get('stats/notes', [AdminController::class, 'statsNotes']);
        
        // Gestion des utilisateurs
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminController::class, 'index']);
            Route::post('/', [AdminController::class, 'store']);
            Route::get('{id}', [AdminController::class, 'show']);
            Route::put('{id}', [AdminController::class, 'update']);
            Route::delete('{id}', [AdminController::class, 'destroy']);
        });
        
        // Gestion des classes
        Route::prefix('classes')->group(function () {
            Route::get('/', [ClasseController::class, 'index']);
            Route::post('/', [ClasseController::class, 'store']);
            Route::get('{id}', [ClasseController::class, 'show']);
            Route::put('{id}', [ClasseController::class, 'update']);
            Route::delete('{id}', [ClasseController::class, 'destroy']);
            Route::get('{id}/eleves', [ClasseController::class, 'eleves']);
        });
        
        // Gestion des enseignants
        Route::prefix('enseignants')->group(function () {
            Route::get('/', [EnseignantController::class, 'index']);
            Route::post('/', [EnseignantController::class, 'store']);
            Route::get('{id}', [EnseignantController::class, 'show']);
            Route::put('{id}', [EnseignantController::class, 'update']);
            Route::delete('{id}', [EnseignantController::class, 'destroy']);
        });
        
        // Gestion des élèves
        Route::prefix('eleves')->group(function () {
            Route::get('/', [EleveController::class, 'index']);
            Route::post('/', [EleveController::class, 'store']);
            Route::get('{id}', [EleveController::class, 'show']);
            Route::put('{id}', [EleveController::class, 'update']);
            Route::delete('{id}', [EleveController::class, 'destroy']);
        });
        
        // Gestion des matières
        Route::prefix('matieres')->group(function () {
            Route::get('/', [MatiereController::class, 'index']);
            Route::post('/', [MatiereController::class, 'store']);
            Route::get('{id}', [MatiereController::class, 'show']);
            Route::put('{id}', [MatiereController::class, 'update']);
            Route::delete('{id}', [MatiereController::class, 'destroy']);
        });
        
        // Gestion des notes
        Route::prefix('notes')->group(function () {
            Route::get('/', [NoteController::class, 'index']);
            Route::post('/', [NoteController::class, 'store']);
            Route::get('{id}', [NoteController::class, 'show']);
            Route::put('{id}', [NoteController::class, 'update']);
            Route::delete('{id}', [NoteController::class, 'destroy']);
            Route::get('classe/{classe_id}', [NoteController::class, 'notesClasse']);
            Route::get('periode/{periode}', [NoteController::class, 'notesPeriode']);
        });
        
        // Gestion des bulletins
        Route::prefix('bulletins')->group(function () {
            Route::get('/', [BulletinController::class, 'index']);
            Route::post('/', [BulletinController::class, 'store']);
            Route::get('{id}', [BulletinController::class, 'show']);
            Route::put('{id}', [BulletinController::class, 'update']);
            Route::delete('{id}', [BulletinController::class, 'destroy']);
            Route::post('{id}/publish', [BulletinController::class, 'publish']);
            Route::get('{id}/pdf', [BulletinController::class, 'generatePdf']);
            Route::post('generate-bulk', [BulletinController::class, 'generateBulk']);
        });
        
        // Gestion des notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('/', [NotificationController::class, 'store']);
            Route::get('{id}', [NotificationController::class, 'show']);
            Route::put('{id}', [NotificationController::class, 'update']);
            Route::delete('{id}', [NotificationController::class, 'destroy']);
            Route::post('send-bulk', [NotificationController::class, 'sendBulk']);
            Route::post('send-to-role', [NotificationController::class, 'sendToRole']);
            Route::get('stats', [NotificationController::class, 'stats']);
            Route::delete('cleanup-old', [NotificationController::class, 'cleanupOld']);
        });
        
        // Gestion des documents
        Route::prefix('documents')->group(function () {
            Route::get('/', [DocumentController::class, 'index']);
            Route::post('/', [DocumentController::class, 'store']);
            Route::get('{id}', [DocumentController::class, 'show']);
            Route::put('{id}', [DocumentController::class, 'update']);
            Route::delete('{id}', [DocumentController::class, 'destroy']);
            Route::post('{id}/validate', [DocumentController::class, 'validate']);
            Route::get('{id}/download', [DocumentController::class, 'download']);
            Route::get('statistiques', [DocumentController::class, 'statistiques']);
            Route::get('en-attente-validation', [DocumentController::class, 'enAttenteValidation']);
            Route::post('valider-en-masse', [DocumentController::class, 'validerEnMasse']);
            Route::delete('nettoyage', [DocumentController::class, 'nettoyage']);
        });
    });
    
    // ========================================
    // ROUTES ENSEIGNANT
    // ========================================
    Route::middleware('role:enseignant')->prefix('enseignant')->group(function () {
        
        // Dashboard et profil
        Route::get('dashboard', [EnseignantController::class, 'dashboard']);
        Route::get('profile', [EnseignantController::class, 'profile']);
        
        // Mes ressources
        Route::get('classes', [EnseignantController::class, 'mesClasses']);
        Route::get('matieres', [EnseignantController::class, 'mesMatieres']);
        Route::get('eleves', [EnseignantController::class, 'mesEleves']);
        
        // Gestion des notes
        Route::prefix('notes')->group(function () {
            Route::get('/', [EnseignantController::class, 'mesNotes']);
            Route::post('/', [EnseignantController::class, 'ajouterNote']);
            Route::put('{id}', [EnseignantController::class, 'modifierNote']);
            Route::delete('{id}', [EnseignantController::class, 'supprimerNote']);
            Route::get('classe/{classe_id}', [EnseignantController::class, 'notesClasse']);
            Route::get('matiere/{matiere_id}', [EnseignantController::class, 'notesMatiere']);
        });
        
        // Consultation des bulletins
        Route::get('bulletins', [EnseignantController::class, 'bulletinsClasses']);
    });
    
    // ========================================
    // ROUTES ÉLÈVE/PARENT
    // ========================================
    Route::middleware('role:eleve')->prefix('eleve')->group(function () {
        
        // Dashboard et profil
        Route::get('dashboard', [EleveController::class, 'dashboard']);
        Route::get('profile', [EleveController::class, 'profile']);
        Route::get('classe', [EleveController::class, 'maClasse']);
        
        // Consultation des notes
        Route::prefix('notes')->group(function () {
            Route::get('/', [EleveController::class, 'mesNotes']);
            Route::get('{periode}', [EleveController::class, 'notesPeriode']);
        });
        
        // Consultation des bulletins
        Route::prefix('bulletins')->group(function () {
            Route::get('/', [EleveController::class, 'mesBulletins']);
            Route::get('{id}', [EleveController::class, 'bulletinDetails']);
        });
        
        // Gestion des documents
        Route::prefix('documents')->group(function () {
            Route::get('/', [EleveController::class, 'mesDocuments']);
            Route::post('/', [DocumentController::class, 'uploadParEleve']);
            Route::get('{id}/download', [DocumentController::class, 'download']);
        });
        
        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [EleveController::class, 'mesNotifications']);
            Route::put('{id}/read', [EleveController::class, 'marquerCommeLu']);
            Route::get('unread/count', [EleveController::class, 'countUnread']);
        });
    });
    
    // ========================================
    // ROUTES COMMUNES À TOUS LES RÔLES
    // ========================================
    Route::prefix('common')->group(function () {
        
        // Notifications
        Route::get('notifications/count', [NotificationController::class, 'countUnread']);
        Route::get('notifications/recent', [NotificationController::class, 'recent']);
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        
        // Informations utilisateur
        Route::get('user/permissions', function () {
            $user = auth()->user();
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'permissions' => [
                        'can_manage_users' => $user->isAdmin(),
                        'can_manage_classes' => $user->isAdmin(),
                        'can_manage_enseignants' => $user->isAdmin(),
                        'can_manage_eleves' => $user->isAdmin(),
                        'can_manage_matieres' => $user->isAdmin(),
                        'can_add_notes' => $user->isEnseignant() || $user->isAdmin(),
                        'can_manage_bulletins' => $user->isAdmin(),
                        'can_view_bulletins' => true,
                        'can_upload_documents' => true,
                        'can_validate_documents' => $user->isAdmin(),
                        'can_send_notifications' => $user->isAdmin(),
                    ]
                ]
            ]);
        });
        
        // Utilitaires
        Route::get('health', function () {
            return response()->json([
                'success' => true,
                'status' => 'healthy',
                'timestamp' => now(),
                'user' => auth()->user()->only(['id', 'nom', 'prenom', 'role']),
                'server_time' => now()->toDateTimeString(),
            ]);
        });
    });
});

// ========================================
// ROUTES D'ERREUR ET FALLBACK
// ========================================

// Route pour les erreurs 404 API
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Route API non trouvée',
        'error' => 'Endpoint non disponible',
        'available_endpoints' => [
            'test' => 'GET /api/test',
            'auth' => [
                'login' => 'POST /api/auth/login',
                'logout' => 'POST /api/auth/logout',
                'profile' => 'GET /api/auth/profile',
                'refresh' => 'POST /api/auth/refresh',
            ],
            'admin' => [
                'dashboard' => 'GET /api/admin/dashboard',
                'users' => 'GET /api/admin/users',
                'classes' => 'GET /api/admin/classes',
                'enseignants' => 'GET /api/admin/enseignants',
                'eleves' => 'GET /api/admin/eleves',
                'matieres' => 'GET /api/admin/matieres',
                'notes' => 'GET /api/admin/notes',
                'bulletins' => 'GET /api/admin/bulletins',
                'notifications' => 'GET /api/admin/notifications',
                'documents' => 'GET /api/admin/documents',
            ],
            'enseignant' => [
                'dashboard' => 'GET /api/enseignant/dashboard',
                'classes' => 'GET /api/enseignant/classes',
                'notes' => 'GET /api/enseignant/notes',
            ],
            'eleve' => [
                'dashboard' => 'GET /api/eleve/dashboard',
                'notes' => 'GET /api/eleve/notes',
                'bulletins' => 'GET /api/eleve/bulletins',
                'documents' => 'GET /api/eleve/documents',
                'notifications' => 'GET /api/eleve/notifications',
            ],
            'common' => [
                'health' => 'GET /api/common/health',
                'permissions' => 'GET /api/common/user/permissions',
            ]
        ]
    ], 404);
});