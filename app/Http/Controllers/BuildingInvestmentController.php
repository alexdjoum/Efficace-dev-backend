<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BuildingInvestment;
use App\Models\Property;

class BuildingInvestmentController extends Controller
{
    public function store(Request $request, $propertyId)
    {
        $property = Property::with('buildingFinance')->findOrFail($propertyId);

        if (!$property->buildingFinance) {
            return response()->json([
                'success' => false,
                'message' => 'Cette property n\'a pas de finance associée'
            ], 404);
        }

        $validated = $request->validate([
            'growth_in_market_value' => 'required|numeric|min:0',
            'annual_expense' => 'required|numeric|min:0',
        ]);

        $investment = BuildingInvestment::updateOrCreate(
            ['building_finance_id' => $property->buildingFinance->id],
            [
                'growth_in_market_value' => $validated['growth_in_market_value'],
                'annual_expense' => $validated['annual_expense'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Investissement créé/mis à jour avec succès',
            'data' => $investment->load('buildingFinance')
        ], 201);
    }
}
