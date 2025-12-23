<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Location;
use App\Models\Property;
use App\Models\ProposedSiteOrLandProposed;

class PropertyService
{
    public function create(array $data)
    {
        $property = Property::create([
            'title' => $data['title'],
            'build_area' => $data['build_area'],
            'field_area' => $data['field_area'],
            'levels' => $data['levels'],
            'has_garden' => $data['has_garden'] ?? false,
            'parkings' => $data['parkings'] ?? 0,
            'has_pool' => $data['has_pool'] ?? false,
            'basement_area' => $data['basement_area'] ?? 0,
            'ground_floor_area' => $data['ground_floor_area'] ?? 0,
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'bedrooms' => $data['bedrooms'] ?? 0,
            'bathrooms' => $data['bathrooms'] ?? 0,
            'estimated_payment' => $data['estimated_payment'] ?? null,
        ]);

        if (isset($data['images']) && is_array($data['images'])) {
            collect($data['images'])->each(function ($item) use ($property) {
                $property->addMedia($item)->toMediaCollection('property');
            });
        }

        if (isset($data['proposed_land_ids']) && is_array($data['proposed_land_ids'])) {
            collect($data['proposed_land_ids'])->each(function ($landId) use ($property) {
                ProposedSiteOrLandProposed::create([
                    'proposable_id' => $landId,
                    'proposable_type' => Land::class,
                ]);
            });
        }

        return $property->fresh();
    }


    public function update(Property $property, array $data)
    {
        $data = is_array($data) ? $data : [];
        
        $property->update($data);

        if (isset($data['images'])) {
            $property->clearMediaCollection('property');

            collect($data['images'])->each(function ($image) use ($property) {
                $property->addMedia($image)->toMediaCollection('property');
            });
        }

        $freshProperty = $property->fresh();
        
        return $freshProperty;
    }
}
