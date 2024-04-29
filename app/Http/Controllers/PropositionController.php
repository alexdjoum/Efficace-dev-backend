<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropositionRequest;
use App\Http\Requests\UpdatePropositionRequest;
use App\Models\Proposition;
use App\Services\PropositionService;
use Illuminate\Support\Facades\DB;

class PropositionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des propositions',
            'data' => Proposition::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePropositionRequest $request, PropositionService $propositionService)
    {
        $request->merge([
            'customer_id' => auth()->user()->userable->id
        ]);

        $proposition = DB::transaction(function () use ($request, $propositionService) {
            return $propositionService->create($request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Proposition crée avec succès.',
            'data' => $proposition
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Proposition $proposition)
    {
        return response()->json([
            'success' => true,
            'message' => 'Détail de la proposition',
            'data' => $proposition
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePropositionRequest $request, Proposition $proposition, PropositionService $propositionService)
    {
        $proposition = DB::transaction(function () use ($request, $proposition, $propositionService) {
            return $propositionService->update($proposition, $request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Proposition mise à jour avec succès.',
            'data' => $proposition
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Proposition $proposition)
    {
        $proposition->delete();

        return response()->json([
            'success' => true,
            'message' => 'Proposition supprimée avec succès.',
            'data' => null
        ]);
    }
}
