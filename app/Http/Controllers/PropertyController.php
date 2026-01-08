<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Models\Property;
use App\Services\PropertyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Property::with([
            'partOfBuildings',
        ]);

        if ($request->has('type') && in_array($request->type, ['villa', 'building'])) {
            $query->where('type', $request->type);
        }

        $properties = $query->get();

        $properties->each(function ($property) {
            if ($property->type === 'building') {
                $property->makeHidden(['bedrooms', 'bathrooms', 'number_of_salons']);
            } else {
                $property->makeHidden(['number_of_appartements', 'part_of_buildings']);
            }

            $property->makeHidden([
                'proposed_sites',
                'accommodations',
                'retail_spaces'
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Liste des propriétés',
            'data' => $properties
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePropertyRequest $request, PropertyService $propertyService)
    {
        try {
            $data = $request->except(['images']);
            
            if ($request->hasFile('images')) {
                $data['images'] = $request->file('images');
            }

            $property = $propertyService->create($data);

            return response()->json([
                'success' => true,
                'message' => 'Propriété créée avec succès.',
                'data' => $property
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Erreur création property', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la propriété',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Property $property)
    {
        return response()->json([
            'success' => true,
            'message' => 'Details de la propriété',
            'data' => $property
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePropertyRequest $request, Property $property, PropertyService $propertyService)
    {

        $property = DB::transaction(function () use ($request, $property, $propertyService) {
            return $propertyService->update($property, $request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Propriété modifié avec succès.',
            'data' => $property
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Property $property)
    {
        $property->location->address()->delete();
        $property->clearMediaCollection('property');
        $property->delete();

        return response()->json([
            'success' => true,
            'message' => 'Propriété supprimée avec succès.',
            'data' => null
        ]);
    }
}
