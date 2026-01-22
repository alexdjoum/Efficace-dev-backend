<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\BuildingFinance;
use Illuminate\Http\Request;

class BuildingFinanceController extends Controller
{
    public function store(Request $request, $propertyId)
    {
        $property = Property::findOrFail($propertyId);

        if ($property->type !== 'building') {
            return response()->json([
                'success' => false,
                'message' => 'The financement cannot to be created'
            ], 400);
        }

        $validated = $request->validate([
            'finances' => 'required|array|min:1|max:3',
            'finances.*.project_study' => 'required|numeric|min:0',
            'finances.*.building_permit' => 'required|numeric|min:0',
            'finances.*.structural_work' => 'required|numeric|min:0',
            'finances.*.finishing' => 'required|numeric|min:0',
            'finances.*.equipments' => 'required|numeric|min:0',
            'finances.*.cost_of_land' => 'required|numeric|min:0',
            'finances.*.type_of_standing' => 'required|string|in:high,medium,low|distinct',
        ]);

        $createdFinances = [];

        foreach ($validated['finances'] as $financeData) {
            $buildingFinance = BuildingFinance::updateOrCreate(
                [
                    'property_id' => $property->id,
                    'type_of_standing' => $financeData['type_of_standing']
                ],
                $financeData
            );

            $createdFinances[] = $buildingFinance;
        }

        $buildingFinance->total_building_finance = round(
            (float) $buildingFinance->project_study 
            + (float) $buildingFinance->building_permit 
            + (float) $buildingFinance->structural_work 
            + (float) $buildingFinance->finishing 
            + (float) $buildingFinance->equipments 
            + (float) $buildingFinance->cost_of_land,
            2
        );

        return response()->json([
            'success' => true,
            'message' => 'Financement created with success',
            'data' => $buildingFinance
        ], 201);
    }
}