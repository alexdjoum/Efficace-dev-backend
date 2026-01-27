<?php

namespace App\Http\Controllers;

// use App\Http\Requests\StoreLandRequest;
use App\Models\Land;
use Illuminate\Http\Request;
use App\Services\LandService;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\LandResource;
use Illuminate\Support\Facades\Redis;
use App\Http\Requests\UpdateLandRequest;

class LandController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $locale = app()->getLocale();
        $cacheKey = "lands:list:{$locale}";

        try {
            $cachedLands = Redis::get($cacheKey);

            if ($cachedLands) {
                return response()->json([
                    'success' => true,
                    'message' => __('messages.lands_list'),
                    'data' => json_decode($cachedLands, true),
                    'from_cache' => true, 
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Redis cache read failed: ' . $e->getMessage());
        }

        $lands = Land::with([
            'fragments',
            'videoLands',
            'location.address',
        ])->get();

        $landsResource = LandResource::collection($lands);

        try {
            Redis::setex($cacheKey, 86400, json_encode($landsResource));
        } catch (\Exception $e) {
            \Log::warning('Redis cache write failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.lands_list'),
            'data' => $landsResource,
            'from_cache' => false, 
        ]);
    }


    public function store(Request $request, LandService $landService)
    {
        try {

            $data = $request->except(['images', 'file']);
            
            if ($request->hasFile('file')) {
                $data['file'] = $request->file('file');
            }
            
            if ($request->hasFile('images')) {
                $data['images'] = $request->file('images');
            }

            $land = $landService->create($data);

            return response()->json([
                'success' => true,
                'message' => 'Terrain créé avec succès',
                'data' => $land
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Erreur création land', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du terrain',
                'error' => $e->getMessage()
            ], 500);
        }
    }

        /**
         * Display the specified resource.
         */
    public function show($id)
    {
        $land = Land::with([
            'fragments',
            'videoLands',
            'location.address',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new LandResource($land)
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
