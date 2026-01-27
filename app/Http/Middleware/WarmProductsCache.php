<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Product;

class WarmProductsCache
{
    public function handle(Request $request, Closure $next)
    {
        $locale = app()->getLocale();
        $cacheKey = "products_list_{$locale}";
        
        // Si le cache n'existe pas, le créer en arrière-plan
        if (!Cache::tags(['products'])->has($cacheKey)) {
            \Log::info("Warming cache for key: {$cacheKey}");
            
            // Lancer le chargement en arrière-plan
            dispatch(function () use ($cacheKey, $locale) {
                app()->setLocale($locale);
                
                $products = Product::with([
                    'productable',
                    'proposedProducts.productable',
                ])
                ->orderBy('created_at', 'desc')
                ->get();
                
                Cache::tags(['products'])->put($cacheKey, $products, 3600);
            })->afterResponse();
        }
        
        return $next($request);
    }
}