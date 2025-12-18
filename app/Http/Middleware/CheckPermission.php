<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        // Autoriser les routes publiques
        if ($request->routeIs(
        'auth.login',
        'auth.register',
        'auth.resend-code',
        'auth.get-reset-code',
        'auth.verify-reset-code',
        'auth.reset-password'
    )) {
        return $next($request);
    }

        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié.'
            ], 401);
        }

        // Admin peut tout faire
        if ($request->user()->isAdmin()) {
            return $next($request);
        }

        // Vérifier si l'utilisateur a au moins une des permissions
        foreach ($permissions as $permission) {
            if ($request->user()->hasPermission($permission)) {
                return $next($request);
            }
        }

        return $next($request);

        // return response()->json([
        //     'success' => false,
        //     'message' => 'Vous n\'avez pas les permissions nécessaires.'
        // ], 403);

        
    }
}