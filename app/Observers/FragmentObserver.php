<?php

namespace App\Observers;

use App\Models\Fragment;
use Illuminate\Support\Facades\Redis;

class FragmentObserver
{
    public function created(Fragment $fragment)
    {
        $this->clearLandCache();
    }

    public function updated(Fragment $fragment)
    {
        $this->clearLandCache();
    }

    public function deleted(Fragment $fragment)
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