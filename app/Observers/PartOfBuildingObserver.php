<?php

namespace App\Observers;

use App\Models\PartOfBuilding;
use Illuminate\Support\Facades\Redis;

class PartOfBuildingObserver
{
    public function created(PartOfBuilding $part)
    {
        $this->clearPropertyCache();
    }

    public function updated(PartOfBuilding $part)
    {
        $this->clearPropertyCache();
    }

    public function deleted(PartOfBuilding $part)
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