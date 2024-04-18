<?php

namespace App\Services;

use App\Models\Virtual;

class VirtualService
{
    public function create(array $data)
    {
        $virtual = Virtual::query()->create($data);

        if (isset($data['images'])) {
            collect($data['images'])->each(function ($image) use ($virtual) {
                $virtual->addMedia($image)->toMediaCollection('virtual');
            });
        }

        return $virtual->fresh();
    }

    public function update(
        $virtual,
        array $data
    ) {
        $virtual->update($data);

        if (isset($data['images'])) {
            $virtual->clearMediaCollection('virtual');
            collect($data['images'])->each(function ($image) use ($virtual) {
                $virtual->addMedia($image)->toMediaCollection('virtual');
            });
        }

        return $virtual->fresh();
    }
}
