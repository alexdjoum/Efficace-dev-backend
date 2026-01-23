<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->input('locale') 
                  ?? $request->header('Accept-Language') 
                  ?? $request->query('locale') 
                  ?? config('app.locale');
        
        if (!in_array($locale, config('app.available_locales'))) {
            $locale = config('app.locale');
        }
        
        App::setLocale($locale);
        
        return $next($request);
    }
}
