<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Land;
use App\Models\Location;


class LandService
{
    public function create(array $data)
    {
        $land = Land::query()->make($data);

        $address = Address::query()->make($data);

        $location = Location::query()->make($data);

        $location->address()->save($address);

        $land->location()->save($location);


        if (isset($data["images"])) {
            collect($data["images"])->each(function ($image) use ($land) {
                $land->addMedia($image)->toMediaCollection('land');
            });
        }

        if (isset($data['fragments'])) {
            collect($data['fragments'])->each(function ($fragment) use ($land) {
                $land->fragments()->create(['area' => $fragment]);
            });
        }


        return $land->fresh();
    }

    public function update(Land $land, array $data)
    {
        $land->location->update($data);
        $land->location->address->update($data);
        $land->update($data);


        if (isset($data['fragments'])) {
            $land->fragments()->delete();

            collect($data['fragments'])->each(function ($fragment) use ($land) {
                $land->fragments()->create(['area' => $fragment]);
            });
        }

        if (isset($data['images'])) {
            $land->clearMediaCollection('land');
            collect($data['images'])->each(function ($image) use ($land) {
                $land->addMedia($image)->toMediaCollection('land');
            });
        }

        return $land->fresh();
    }
}
