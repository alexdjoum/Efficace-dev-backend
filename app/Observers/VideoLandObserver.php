<?php

namespace App\Observers;

use App\Models\VideoLand;
use Illuminate\Support\Facades\Redis;

class VideoLandObserver
{
    public function created(VideoLand $videoLand)
    {
        $this->clearLandCache();
    }

    public function updated(VideoLand $videoLand)
    {
        $this->clearLandCache();
    }

    public function deleted(VideoLand $videoLand)
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