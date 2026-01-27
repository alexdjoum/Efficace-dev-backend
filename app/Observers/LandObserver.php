<?php

namespace App\Observers;

use App\Models\Land;
use Illuminate\Support\Facades\Redis;

class LandObserver
{

    public function created(Land $land)
    {
        $this->clearLandCache();
    }

    public function updated(Land $land)
    {
        $this->clearLandCache();
    }

    public function deleted(Land $land)
    {
        $this->clearLandCache();
    }

    protected function clearLandCache()
    {
        try {
            $keys = Redis::keys('lands:*');
            if (!empty($keys)) {
                Redis::del($keys);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to clear land cache: ' . $e->getMessage());
        }
    }
}