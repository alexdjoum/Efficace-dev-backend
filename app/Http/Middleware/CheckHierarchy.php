<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckHierarchy
{
    public function handle(Request $request, Closure $next, $minLevel)
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

        if ($request->user()->getHighestHierarchyLevel() < $minLevel) {
            return response()->json([
                'success' => false,
                'message' => 'Niveau hiérarchique insuffisant.'
            ], 403);
        }

        return $next($request);
    }
}
