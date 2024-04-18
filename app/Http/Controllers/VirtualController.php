<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVirtualRequest;
use App\Http\Requests\UpdateVirtualRequest;
use App\Models\Virtual;
use App\Services\VirtualService;
use Illuminate\Support\Facades\DB;

class VirtualController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des virtuels',
            'virtuals' => Virtual::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVirtualRequest $request, VirtualService $virtualService)
    {
        $virtual = DB::transaction(function () use ($request, $virtualService) {
            return $virtualService->create($request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Virtuel crée avec succès',
            'data' => $virtual
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Virtual $virtual)
    {
        return response()->json([
            'success' => true,
            'message' => 'Details du virtuel',
            'data' => $virtual
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVirtualRequest $request, Virtual $virtual, VirtualService $virtualService)
    {
        $virtual = DB::transaction(function () use ($request, $virtual, $virtualService) {
            return $virtualService->update($virtual, $request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Virtuel mis à jour avec succès',
            'data' => $virtual
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Virtual $virtual)
    {
        $virtual->clearMediaCollection('virtual');
        $virtual->delete();
        return response()->json([
            'success' => true,
            'message' => 'Virtuel supprimé avec succès',
            'data' => null
        ]);
    }
}
