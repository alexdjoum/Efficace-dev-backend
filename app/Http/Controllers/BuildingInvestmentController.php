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
            'operating_ratio_excluding_tax_id' => 'required|exists:operating_ratio_excluding_taxes,id',
            'growth_in_market_value' => 'nullable|numeric|min:0',
            'annual_expense' => 'nullable|numeric|min:0',
        ]);

        $investment = BuildingInvestment::create([
            'building_finance_id' => $property->buildingFinance->id,
            'operating_ratio_excluding_tax_id' => $validated['operating_ratio_excluding_tax_id'],
            'growth_in_market_value' => $validated['growth_in_market_value'] ?? null,
            'annual_expense' => $validated['annual_expense'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Investissement créé avec succès',
            'data' => $investment->load(['buildingFinance', 'operatingRatio'])
        ], 201);
    }
}
