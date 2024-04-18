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
            return RetailSpace::create($request->all());
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
        $retail = DB::transaction(function () use ($request, $retailSpace) {
            return $retailSpace->update($request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Espace commercial modifié avec succès.',
            'data' => $retail
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RetailSpace $retailSpace)
    {
        return response()->json([
            'success' => true,
            'message' => 'Espace commercial supprimé avec succès.',
            'data' => $retailSpace->delete()
        ]);
    }
}
