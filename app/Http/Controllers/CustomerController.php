<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des clients',
            'data' =>  Customer::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, CustomerService $customerService)
    {
        $validator = validator()->make($request->all(), [
            "first_name" => "required|string",
            "last_name" => "required|string",
            "email" => "required|email|unique:users",
            "phone" => "required|string",
            "password" => "required|string|min:6|confirmed",
            "country" => "required|string",
            "city" => "required|string",
            "street" => "required|string",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => "Erreurs de validation.",
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        $customer = DB::transaction(function () use ($request, $customerService) {
            return $customerService->create($request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Client crée avec succès.',
            'data' => $customer
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        return response()->json([
            'success' => true,
            'message' => 'Détails du client',
            'data' => $customer
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer, CustomerService $customerService)
    {
        $validator = validator()->make($request->all(), [
            "first_name" => "sometimes|required|string",
            "last_name" => "sometimes|required|string",
            "email" => "sometimes|required|email|unique:users,email," . $customer->id,
            "phone" => "sometimes|required|string",
            "country" => "sometimes|required|string",
            "city" => "sometimes|required|string",
            "street" => "sometimes|required|string",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => "Erreurs de validation.",
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        $customer = DB::transaction(function () use ($request, $customer, $customerService) {
            return $customerService->update($customer, $request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Client mis à jour avec succès.',
            'data' => $customer
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Client mise à jour avec succès.',
            'data' => null
        ], 204);
    }
}