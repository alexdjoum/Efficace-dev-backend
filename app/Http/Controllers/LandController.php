<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLandRequest;
use App\Http\Requests\UpdateLandRequest;
use App\Models\Land;
use App\Services\LandService;
use Illuminate\Support\Facades\DB;

class LandController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des terrains',
            'data' => Land::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLandRequest $request, LandService $landService)
    {
        $land = DB::transaction(function () use ($request, $landService) {
            return $landService->create($request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Terrain créé avec succès',
            'data' => $land
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Land $land)
    {
        return response()->json([
            'success' => true,
            'message' => 'Détails du terrain',
            'data' => $land
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLandRequest $request, Land $land, LandService $landService)
    {
        $land = DB::transaction(function () use ($request, $land, $landService) {
            return $landService->update($land, $request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Terrain mis à jour avec succès',
            'data' => $land
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Land $land)
    {
        $land->location->address()->delete();
        $land->clearMediaCollection('land');
        $land->delete();

        return response()->json([
            'success' => true,
            'message' => 'Terrain supprimé avec succès',
            'data' => null
        ]);
    }
}
