<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Location;
use App\Models\Property;

class PropertyService
{
    public function create(array $data)
    {
        $address = Address::query()->make($data);
        $location = Location::query()->make($data);
        $location->save();
        $location->address()->save($address);
        $property = Property::query()->make($data);
        $property->location()->associate($location);
        // dd($property, $address, $location);
        $property->save();

        if (isset($data['images'])) {
            collect($data['images'])->each(function ($item) use ($property) {
                $property->addMedia($item)->toMediaCollection('property');
            });
        }

        return $property->fresh();
    }


    public function update(Property $property, array $data)
    {
        $property->update($data);

        $property->location->update($data);

        $property->location->address->update($data);

        if (isset($data['images'])) {
            $property->clearMediaCollection('property');

            collect($data['images'])->each(function ($image) use ($property) {
                $property->addMedia($image)->toMediaCollection('property');
            });
        }


        return $property->fresh();
    }
}
