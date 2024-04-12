<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Location;
use App\Models\Property;

class PropertyService
{
    public function create(array $data)
    {
        $address = Address::query()->create($data);
        $location = Location::query()->create($data);
        $location->address()->save($address);

        $property = Property::query()->create($data);
        $property->location()->save($location);

        if (isset($data['images'])) {

            collect($data['images'])->each(function ($item) use ($property) {
                $property->addMedia($item)->toMediaCollection('property');
            });
        }

        $property->fresh();

        return $property;
    }


    public function update(Property $property, array $data)
    {
        $property->update($data);

        $property->location()->update($data);

        $property->location->address()->update($data);

        if (isset($data['images'])) {
            $property->clearMediaCollection('property');

            collect($data['images'])->each(function ($image) use ($property) {
                $property->addMedia($image)->toMediaCollection('property');
            });
        }

        $property->fresh();

        return $property;
    }
}
