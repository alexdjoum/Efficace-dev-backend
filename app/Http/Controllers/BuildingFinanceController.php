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
            'project_study' => 'required|numeric|min:0',
            'building_permit' => 'required|numeric|min:0',
            'structural_work' => 'required|numeric|min:0',
            'finishing' => 'required|numeric|min:0',
            'equipments' => 'required|numeric|min:0',
            'total_excluding_field' => 'required|numeric|min:0',
            'cost_of_land' => 'required|numeric|min:0',
        ]);

        $buildingFinance = BuildingFinance::updateOrCreate(
            ['property_id' => $property->id],
            $validated
        );

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