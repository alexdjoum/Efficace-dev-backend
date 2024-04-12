<?php

namespace App\Http\Controllers;

use App\Models\Accommodation;
use Illuminate\Support\Facades\DB;
use App\Services\AccommodationService;
use App\Http\Requests\StoreAccommodationRequest;
use App\Http\Requests\UpdateAccommodationRequest;

class AccommodationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des logements',
            'data' => Accommodation::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAccommodationRequest $request, AccommodationService $accommodationService)
    {
        $accommodation = DB::transaction(function () use ($request, $accommodationService) {
            return $accommodationService->create($request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Logement créé avec succès',
            'data' => $accommodation
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Accommodation $accommodation)
    {
        return response()->json([
            'success' => true,
            'message' => 'Détails du logement',
            'data' => $accommodation
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAccommodationRequest $request, Accommodation $accommodation, AccommodationService $accommodationService)
    {
        $accommodation = DB::transaction(function () use ($request, $accommodation, $accommodationService) {
            return $accommodationService->update($accommodation, $request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Logement mis à jour avec succès',
            'data' => $accommodation
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Accommodation $accommodation)
    {
        $accommodation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logement supprimé avec succès',
            'data' => null
        ]);
    }
}
