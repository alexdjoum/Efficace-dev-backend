<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BuildingInvestment;
use App\Models\Property;

class BuildingInvestmentController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/properties/{propertyId}/investment",
     *     summary="Créer ou mettre à jour un investissement",
     *     tags={"Building Investments"},
     *     @OA\Parameter(
     *         name="propertyId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"growth_in_market_value","annual_expense","mount_income"},
     *             @OA\Property(property="growth_in_market_value", type="number", example=5.5),
     *             @OA\Property(property="annual_expense", type="number", example=1500000),
     *             @OA\Property(property="mount_income", type="number", example=3000000)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Investissement créé/mis à jour")
     * )
     */
    public function store(Request $request, $propertyId)
    {
        $property = Property::findOrFail($propertyId);

        $validated = $request->validate([
            'growth_in_market_value' => 'required|numeric|min:0',
            'annual_expense' => 'required|numeric|min:0',
            'mount_income' => 'required|numeric|min:0',
        ]);

        $investment = BuildingInvestment::updateOrCreate(
            ['property_id' => $propertyId],
            [
                'growth_in_market_value' => $validated['growth_in_market_value'],
                'annual_expense' => $validated['annual_expense'],
                'mount_income' => $validated['mount_income'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Investissement créé/mis à jour avec succès',
            'data' => $investment->load('property')
        ], 201);
    }
}
