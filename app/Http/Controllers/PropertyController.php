<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Models\Property;
use App\Services\PropertyService;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des propriétés',
            'data' => Property::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePropertyRequest $request, PropertyService $propertyService)
    {
        $property = DB::transaction(function () use ($request, $propertyService) {
            return $propertyService->create($request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Propriété crée avec succès.',
            'data' => $property
        ], 201);
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
