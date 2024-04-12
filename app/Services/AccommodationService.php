<?php

namespace App\Services;

use App\Models\Accommodation;

class AccommodationService
{
    public function create(array $data)
    {
        $accommodation = Accommodation::query()->create($data);

        if (isset($data['images'])) {
            collect($data['images'])->each(function ($image) use ($accommodation) {
                $accommodation->addMedia($image)->toMediaCollection('accommodation');
            });
        }

        $accommodation->fresh();

        return $accommodation;
    }


    public function update(Accommodation $accommodation, array $data)
    {
        $accommodation->update($data);

        if (isset($data['images'])) {
            $accommodation->clearMediaCollection('accommodation');

            collect($data['images'])->each(function ($image) use ($accommodation) {
                $accommodation->addMedia($image)->toMediaCollection('accommodation');
            });
        }

        $accommodation->fresh();

        return $accommodation;
    }
}
