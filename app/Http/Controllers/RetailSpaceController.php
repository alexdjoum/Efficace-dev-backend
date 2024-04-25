<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRetailSpaceRequest;
use App\Http\Requests\UpdateRetailSpaceRequest;
use App\Models\RetailSpace;
use Illuminate\Support\Facades\DB;

class RetailSpaceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des espaces commerciaux',
            'data' => RetailSpace::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRetailSpaceRequest $request)
    {
        $retail = DB::transaction(function () use ($request) {
            $retail = RetailSpace::create($request->all());
            if ($request->has('images')) {
                collect($request->images)->each(function ($image) use ($retail) {
                    $retail->addMedia($image)->toMediaCollection('retail_space');
                });
            }

            return $retail;
        });

        return response()->json([
            'success' => true,
            'message' => 'Espace commercial crée avec succès.',
            'data' => $retail
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(RetailSpace $retailSpace)
    {
        return response()->json([
            'success' => true,
            'message' => 'Details de l\'espace commercial',
            'data' => $retailSpace
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRetailSpaceRequest $request, RetailSpace $retailSpace)
    {
        DB::transaction(function () use ($request, $retailSpace) {
            $retailSpace->update($request->all());
            if ($request->has('images')) {
                $retailSpace->clearMediaCollection('retail_space');
                collect($request->images)->each(function ($image) use ($retailSpace) {
                    $retailSpace->addMedia($image)->toMediaCollection('retail_space');
                });
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Espace commercial modifié avec succès.',
            'data' => $retailSpace->refresh()
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RetailSpace $retailSpace)
    {
        $retailSpace->clearMediaCollection('retail_space');
        $retailSpace->delete();
        return response()->json([
            'success' => true,
            'message' => 'Espace commercial supprimé avec succès.',
            'data' => null
        ]);
    }
}
