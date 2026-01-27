<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Models\Product;

class ManageProductsCache extends Command
{
    protected $signature = 'products:cache {action : clear|warm|stats}';
    protected $description = 'Manage products cache (clear, warm, or view stats)';

    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'clear':
                $this->clearCache();
                break;
            case 'warm':
                $this->warmCache();
                break;
            case 'stats':
                $this->showStats();
                break;
            default:
                $this->error("Unknown action: {$action}");
                return 1;
        }

        return 0;
    }

    protected function clearCache()
    {
        Cache::tags(['products'])->flush();
        $this->info('✅ Products cache cleared successfully!');
    }

    protected function warmCache()
    {
        $this->info('Warming products cache...');
        
        $locales = ['fr', 'en'];
        
        foreach ($locales as $locale) {
            app()->setLocale($locale);
            $cacheKey = "products_list_{$locale}";
            
            $this->info("Loading products for locale: {$locale}");
            
            $products = Product::with([
                'productable',
                'proposedProducts.productable',
            ])
            ->orderBy('created_at', 'desc')
            ->get();
            
            Cache::tags(['products'])->put($cacheKey, $products, 3600);
            
            $this->info("✅ Cache warmed for locale: {$locale}");
        }
        
        $this->info('✅ All caches warmed successfully!');
    }

    protected function showStats()
    {
        $this->info('Cache Statistics:');
        $this->table(
            ['Key', 'Exists', 'Size'],
            [
                [
                    'products_list_fr',
                    Cache::tags(['products'])->has('products_list_fr') ? '✅' : '❌',
                    'N/A'
                ],
                [
                    'products_list_en',
                    Cache::tags(['products'])->has('products_list_en') ? '✅' : '❌',
                    'N/A'
                ],
            ]
        );
    }
}