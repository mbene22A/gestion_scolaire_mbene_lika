<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Vérifier si l'utilisateur est authentifié
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n etes pas authentifié',
                'error' => 'Token expire'
            ], 401);
        }

        $user = auth()->user();
        
        // Vérifier des roles pour les utilisateurs
        if (!in_array($user->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé pour ce rôle',
                'error' => 'Rôle requis: ' . implode(', ', $roles) . '. Rôle actuel: ' . $user->role
            ], 403);
        }

        return $next($request);
    }
}