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

        if (isset($data['land_title'])) {
            $land->addMedia($data['land_title'])->toMediaCollection('land_tile');
        }
        if (isset($data['certificat_of_ownership'])) {
            $land->addMedia($data['certificat_of_ownership'])->toMediaCollection('certificat_of_ownership');
        }
        if (isset($data['technical_doc'])) {
            $land->addMedia($data['technical_doc'])->toMediaCollection('technical_doc');
        }

        if (isset($data["images"])) {
            collect($data["images"])->each(function ($image) use ($land) {
                $land->addMedia($image)->toMediaCollection('land');
            });
        }


        return $land->fresh();
    }

    public function update(Land $land, array $data)
    {
        $land->location->update($data);
        $land->location->address->update($data);
        $land->update($data);

        if (isset($data['land_title'])) {
            $land->addMedia($data['land_title'])->toMediaCollection('land_tile');
        }
        if (isset($data['certificat_of_ownership'])) {
            $land->addMedia($data['certificat_of_ownership'])->toMediaCollection('certificat_of_ownership');
        }
        if (isset($data['technical_doc'])) {
            $land->addMedia($data['technical_doc'])->toMediaCollection('documents');
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
