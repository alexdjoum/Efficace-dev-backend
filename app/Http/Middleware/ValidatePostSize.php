<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidatePostSize
{
    public function handle(Request $request, Closure $next)
    {
        $maxSize = 100 * 1024 * 1024; 

        if ($request->server('CONTENT_LENGTH') > $maxSize) {
            return response()->json([
                'success' => false,
                'message' => 'Le fichier est trop volumineux. Maximum 100MB.',
            ], 413);
        }

        return $next($request);
    }
}