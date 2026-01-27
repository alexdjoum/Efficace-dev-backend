<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    const CACHE_TAG = 'products';

    protected function clearCache()
    {
        \Log::info('Clearing products cache due to model change');
        
        Cache::tags([self::CACHE_TAG])->flush();
    }

    public function created(Product $product)
    {
        $this->clearCache();
    }

    public function updated(Product $product)
    {
        $this->clearCache();
    }

    public function deleted(Product $product)
    {
        $this->clearCache();
    }

    public function restored(Product $product)
    {
        $this->clearCache();
    }
}