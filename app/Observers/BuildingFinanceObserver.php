<?php

namespace App\Observers;

use App\Models\BuildingFinance;
use Illuminate\Support\Facades\Redis;

class BuildingFinanceObserver
{
    public function created(BuildingFinance $buildingFinance)
    {
        $this->clearPropertyCache();
    }

    public function updated(BuildingFinance $buildingFinance)
    {
        $this->clearPropertyCache();
    }

    public function deleted(BuildingFinance $buildingFinance)
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