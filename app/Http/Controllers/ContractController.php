<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Http\Request;

class ContractController extends Controller
{

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'terms' => 'required',
            'document' => 'required|file',
            'contractable_id' => 'required',
            'type' => 'required|in:proposition,investment',
        ]);

        $request->merge([
            'contractable_type' => $request->type == 'proposition' ? 'App\Models\Proposition' : 'App\Models\Investment',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Erreurs de validation.',
                'data' => ['errors' => $validator->errors()],
            ], 422);
        }

        $file = $request->file('document');

        $contract = Contract::create($request->all());

        $contract->addMedia($file)->toMediaCollection('contract');

        return response()->json([
            'status' => true,
            'message' => 'Contrat crée avec succès.',
            'data' => $contract->fresh()
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Contract $contract)
    {
        $validator = validator()->make($request->all(), [
            'terms' => 'sometimes|required',
            'document' => 'sometimes|required|file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Erreurs de validation.',
                'data' => ['errors' => $validator->errors()],
            ], 422);
        }

        $contract->update($request->all());

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $contract->addMedia($file)->toMediaCollection('contract');
        }

        return response()->json([
            'status' => true,
            'message' => 'Contrat mis à jour avec succès.',
            'data' => $contract->fresh()
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contract $contract)
    {
        $contract->clearMediaCollection('contract');
        $contract->delete();

        return response()->json([
            'status' => true,
            'message' => 'Contrat supprimé avec succès.',
            'data' => null
        ]);
    }
}