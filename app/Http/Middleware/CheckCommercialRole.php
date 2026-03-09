<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckCommercialRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user || !$user->hasRole('commercial')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux commerciaux',
            ], 403);
        }

        return $next($request);
    }
}