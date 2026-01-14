<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Property;
use App\Models\OperatingRatioExcludingTax;

class OperatingRatioExcludingTaxController extends Controller
{
    public function store(Request $request, $propertyId)
    {
        $property = Property::findOrFail($propertyId);
        
        if ($property->type !== 'building') {
            return response()->json([
                'success' => false,
                'message' => 'Les ratios ne peuvent être créés que pour les buildings.'
            ], 400);
        }

        $validated = $request->validate([
            'ratios' => 'required|array',
            'ratios.*.type' => 'required|string',
            'ratios.*.montant' => 'required|numeric|min:0',
        ]);

        $createdRatios = [];

        foreach ($validated['ratios'] as $ratioData) {
            $ratio = OperatingRatioExcludingTax::updateOrCreate(
                [
                    'property_id' => $propertyId,
                    'type' => $ratioData['type']
                ],
                [
                    'montant' => $ratioData['montant']
                ]
            );
            
            $createdRatios[] = $ratio;
        }

        return response()->json([
            'success' => true,
            'message' => 'Ratios d\'exploitation créés/mis à jour avec succès',
            'data' => $createdRatios
        ], 201);
    }
}
