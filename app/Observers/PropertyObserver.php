<?php

namespace App\Observers;

use App\Models\Property;
use Illuminate\Support\Facades\Redis;

class PropertyObserver
{
    public function created(Property $property)
    {
        $this->clearPropertyCache();
    }

    public function updated(Property $property)
    {
        $this->clearPropertyCache();
    }

    public function deleted(Property $property)
    {
        $this->clearPropertyCache();
    }


    protected function clearPropertyCache()
    {
        try {
            $keys = Redis::keys('properties:*');
            if (!empty($keys)) {
                Redis::del($keys);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to clear property cache: ' . $e->getMessage());
        }
    }
}