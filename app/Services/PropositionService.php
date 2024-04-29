<?php

namespace App\Services;

use App\Models\Land;
use App\Models\Virtual;
use App\Models\Property;
use App\Models\Proposition;
use App\Models\RetailSpace;
use App\Models\Accommodation;

class PropositionService
{
    public function create(array $data)
    {
        $proposition = Proposition::query()->make($data);

        switch ($data['type']) {
            case 'land':
                $land = Land::query()->findOrFail($data['proposable_id']);
                $proposition->proposable()->associate($land);
                $proposition->save();
                break;
            case 'property':
                $property = Property::query()->findOrFail($data['proposable_id']);
                $proposition->proposable()->associate($property);
                $proposition->save();
                break;
            case 'accommodation':
                $accommodation = Accommodation::query()->findOrFail($data['proposable_id']);
                $proposition->proposable()->associate($accommodation);
                $proposition->save();
                break;
            case 'virtual':
                $virtual = Virtual::query()->findOrFail($data['proposable_id']);
                $proposition->proposable()->associate($virtual);
                $proposition->save();
                break;
            case 'retail_space':
                $retail_space = RetailSpace::query()->findOrFail($data['proposable_id']);
                $proposition->proposable()->associate($retail_space);
                $proposition->save();
                break;
        }

        return $proposition->fresh();
    }


    public function update(Proposition $proposition, array $data)
    {
        $proposition->update($data);

        return $proposition->fresh();
    }
}
