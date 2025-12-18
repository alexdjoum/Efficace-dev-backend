<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // ✅ Pour une API, retourner null au lieu de rediriger
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        // Pour les routes web classiques (si vous en avez)
        return route('login');
    }

    
}
