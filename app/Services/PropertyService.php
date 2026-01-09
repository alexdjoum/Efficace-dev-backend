<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Location;
use App\Models\Property;
use App\Models\ProposedSiteOrLandProposed;
use App\Models\Land;

class PropertyService
{
    public function create(array $data)
    {
        if ($data['type'] === Property::TYPE_BUILDING) {
            $data['bedrooms'] = null;
            $data['bathrooms'] = null;
            $data['number_of_salons'] = null;
        } else {
            $data['bedrooms'] = $data['bedrooms'];
            $data['bathrooms'] = $data['bathrooms'];
            $data['number_of_salons'] = $data['number_of_salons'];
            $data['number_of_appartements'] = null;
        }
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
            'number_of_salons' => $data['number_of_salons'] ?? 0,
            'number_of_appartements' => $data['type'] === Property::TYPE_BUILDING 
            ? ($data['number_of_appartements'] ?? null)
            : null, 
            'estimated_payment' => $data['estimated_payment'] ?? null,
        ]);


        if ($data['type'] === Property::TYPE_BUILDING) {
            if ($data['type'] === Property::TYPE_BUILDING && isset($data['parts_of_building']) && is_array($data['parts_of_building'])) {
                foreach ($data['parts_of_building'] as $index => $part) {
                    $partOfBuilding = $property->partOfBuildings()->create([
                        'title' => $part['title'],
                        'description' => $part['description'] ?? null,
                    ]);

                    if (isset($data["part_photos_{$index}"]) && is_array($data["part_photos_{$index}"])) {
                        foreach ($data["part_photos_{$index}"] as $photo) {
                            if ($photo instanceof \Illuminate\Http\UploadedFile) {
                                $media = $partOfBuilding->addMedia($photo)
                                    ->usingFileName($photo->getClientOriginalName())
                                    ->toMediaCollection('part_photos');
                                
                                \App\Models\PhotoPartBuilding::create([
                                    'path_part_building' => str_replace(public_path() . '/', '', $media->getPath()),
                                    'part_of_building_id' => $partOfBuilding->id,
                                ]);
                            }
                        }
                    }
                }
            }

            if (isset($data['building_finance'])) {
                $property->buildingFinance()->create([
                    'project_study' => $data['building_finance']['project_study'] ?? null,
                    'building_permit' => $data['building_finance']['building_permit'] ?? null,
                    'structural_work' => $data['building_finance']['structural_work'] ?? null,
                    'finishing' => $data['building_finance']['finishing'] ?? null,
                    'equipments' => $data['building_finance']['equipments'] ?? null,
                    'total_excluding_field' => $data['building_finance']['total_excluding_field'] ?? null,
                    'cost_of_land' => $data['building_finance']['cost_of_land'] ?? null,
                ]);
            }
        }

        if (isset($data['images']) && is_array($data['images'])) {
            foreach ($data['images'] as $image) {
                if ($image instanceof \Illuminate\Http\UploadedFile) {
                    $property->addMedia($image)->toMediaCollection('property');
                }
            }
        }

        return $property->fresh([
            'partOfBuildings',
            'buildingFinance',
            'partOfBuildings.photos',
            'proposedSites.proposable' => function ($query) {
                $query->with([
                    'location.address',   
                    'location.media',     
                    'fragments',
                    'videoLands'
                ])
                ->without(['accommodations', 'retail_spaces', 'proposedSites']);
            },
            'media',
            'accommodations',
            'retail_spaces'
        ]);
    }

    public function update(Property $property, array $data)
    {
        $data = is_array($data) ? $data : [];
        
        $typeToUpdate = $data['type'] ?? $property->type;
        
        if ($typeToUpdate === Property::TYPE_BUILDING) {
            $data['bedrooms'] = null;
            $data['bathrooms'] = null;
            $data['number_of_salons'] = null;
        } else {
            $data['number_of_appartements'] = null;
        }
        
        $property->update([
            'title' => $data['title'] ?? $property->title,
            'build_area' => $data['build_area'] ?? $property->build_area,
            'field_area' => $data['field_area'] ?? $property->field_area,
            'levels' => $data['levels'] ?? $property->levels,
            'has_garden' => $data['has_garden'] ?? $property->has_garden,
            'parkings' => $data['parkings'] ?? $property->parkings,
            'has_pool' => $data['has_pool'] ?? $property->has_pool,
            'basement_area' => $data['basement_area'] ?? $property->basement_area,
            'ground_floor_area' => $data['ground_floor_area'] ?? $property->ground_floor_area,
            'type' => $data['type'] ?? $property->type,
            'description' => $data['description'] ?? $property->description,
            'bedrooms' => $data['bedrooms'] ?? $property->bedrooms,
            'bathrooms' => $data['bathrooms'] ?? $property->bathrooms,
            'number_of_salons' => $data['number_of_salons'] ?? $property->number_of_salons,
            'number_of_appartements' => $data['number_of_appartements'] ?? $property->number_of_appartements, 
            'estimated_payment' => $data['estimated_payment'] ?? $property->estimated_payment,
        ]);

        if (isset($data['images']) && is_array($data['images'])) {
            $property->clearMediaCollection('property');

            foreach ($data['images'] as $image) {
                if ($image instanceof \Illuminate\Http\UploadedFile) {
                    $property->addMedia($image)->toMediaCollection('property');
                }
            }
        }

        if ($property->type === Property::TYPE_BUILDING && isset($data['parts_of_building']) && is_array($data['parts_of_building'])) {
            
            foreach ($data['parts_of_building'] as $index => $partData) {
                $partOfBuilding = null;
                
                if (isset($partData['id'])) {
                    $partOfBuilding = \App\Models\PartOfBuilding::find($partData['id']);
                } else {
                    $partOfBuilding = $property->partOfBuildings()
                        ->where('title', $partData['title'])
                        ->first();
                }
                
                if ($partOfBuilding && $partOfBuilding->property_id === $property->id) {
                    $partOfBuilding->update([
                        'title' => $partData['title'] ?? $partOfBuilding->title,
                        'description' => $partData['description'] ?? $partOfBuilding->description,
                    ]);

                    if (isset($data["part_photos_{$index}"]) && is_array($data["part_photos_{$index}"]) && !empty($data["part_photos_{$index}"])) {
                        $partOfBuilding->clearMediaCollection('part_photos');
                        \App\Models\PhotoPartBuilding::where('part_of_building_id', $partOfBuilding->id)->delete();


                        foreach ($data["part_photos_{$index}"] as $photo) {
                            if ($photo instanceof \Illuminate\Http\UploadedFile) {
                                $media = $partOfBuilding->addMedia($photo)
                                    ->usingFileName($photo->getClientOriginalName())
                                    ->toMediaCollection('part_photos');
                                
                                \App\Models\PhotoPartBuilding::create([
                                    'path_part_building' => str_replace(public_path() . '/', '', $media->getPath()),
                                    'part_of_building_id' => $partOfBuilding->id,
                                ]);
                            }
                        }
                    }
                } else {
                    $partOfBuilding = $property->partOfBuildings()->create([
                        'title' => $partData['title'],
                        'description' => $partData['description'] ?? null,
                    ]);

                    if (isset($data["part_photos_{$index}"]) && is_array($data["part_photos_{$index}"])) {
                        foreach ($data["part_photos_{$index}"] as $photo) {
                            if ($photo instanceof \Illuminate\Http\UploadedFile) {
                                $media = $partOfBuilding->addMedia($photo)
                                    ->usingFileName($photo->getClientOriginalName())
                                    ->toMediaCollection('part_photos');
                                
                                \App\Models\PhotoPartBuilding::create([
                                    'path_part_building' => str_replace(public_path() . '/', '', $media->getPath()),
                                    'part_of_building_id' => $partOfBuilding->id,
                                ]);
                            }
                        }
                    }
                }
            }
        }

        if ($property->type === Property::TYPE_BUILDING && isset($data['building_finance'])) {
            if ($property->buildingFinance) {
                // Mise à jour
                $property->buildingFinance->update([
                    'project_study' => $data['building_finance']['project_study'] ?? $property->buildingFinance->project_study,
                    'building_permit' => $data['building_finance']['building_permit'] ?? $property->buildingFinance->building_permit,
                    'structural_work' => $data['building_finance']['structural_work'] ?? $property->buildingFinance->structural_work,
                    'finishing' => $data['building_finance']['finishing'] ?? $property->buildingFinance->finishing,
                    'equipments' => $data['building_finance']['equipments'] ?? $property->buildingFinance->equipments,
                    'total_excluding_field' => $data['building_finance']['total_excluding_field'] ?? $property->buildingFinance->total_excluding_field,
                    'cost_of_land' => $data['building_finance']['cost_of_land'] ?? $property->buildingFinance->cost_of_land,
                ]);
            } else {
                // Création
                $property->buildingFinance()->create([
                    'project_study' => $data['building_finance']['project_study'] ?? null,
                    'building_permit' => $data['building_finance']['building_permit'] ?? null,
                    'structural_work' => $data['building_finance']['structural_work'] ?? null,
                    'finishing' => $data['building_finance']['finishing'] ?? null,
                    'equipments' => $data['building_finance']['equipments'] ?? null,
                    'total_excluding_field' => $data['building_finance']['total_excluding_field'] ?? null,
                    'cost_of_land' => $data['building_finance']['cost_of_land'] ?? null,
                ]);
            }
        }

        return $property->fresh([
            'partOfBuildings',
            'buildingFinance',
            'media',
            'location.address'
        ]);
    }
}
