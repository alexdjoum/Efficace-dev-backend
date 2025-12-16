<?php

namespace App\Http\Controllers;

// use App\Http\Requests\StoreLandRequest;
use Illuminate\Http\Request;
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
        $lands = Land::with([
            // Sélectionner uniquement les champs nécessaires pour fragments
            'fragments:id,area',
            // Sélectionner uniquement les champs nécessaires pour videoLands
            'videoLands:land_id,videoLink',
            // Charger location avec address, mais seulement coordinate_link et address fields
            'location:id,coordinate_link',
            'location.address:id,addressable_id,street,city,country'
        ])->get()->map(function ($land) {
            // Transformer les images pour ne renvoyer que les URLs
            $land->images = $land->getMedia('land')->map(fn($media) => $media->getUrl());

            // Replacer location.address pour ne garder que les champs demandés
            if ($land->location && $land->location->address) {
                $land->location->address = [
                    'street' => $land->location->address->street,
                    'city' => $land->location->address->city,
                    'country' => $land->location->address->country,
                ];
            }

            // Replacer location pour ne garder que address et coordinate_link
            if ($land->location) {
                $land->location = [
                    'coordinate_link' => $land->location->coordinate_link,
                    'address' => $land->location->address
                ];
            }

            return $land;
        });

        return response()->json([
            'success' => true,
            'message' => 'Liste des terrains',
            'data' => $lands
        ]);
    }


    public function store(Request $request, LandService $landService)
    {
        $data = $request->except(['images', 'file']);

        $data['images'] = $request->file('images');
        $data['file']   = $request->file('file');

        $land = $landService->create($data);

        return response()->json([
            'message' => 'Land created successfully',
            'data' => $land
        ]);
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
