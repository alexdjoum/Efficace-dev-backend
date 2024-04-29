<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des commandes',
            'data' => Order::all()
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'customer_id' => auth()->user()->userable->id
        ]);

        $validator = validator()->make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'unit_price' => 'required|numeric',
            'total_price' => 'required|numeric',
            'status' => 'required',
            'customer_id' => 'required|exists:customers,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => $validator->errors()
            ], 422);
        }

        $order = Order::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Commande crée avec succès.',
            'data' => $order->fresh()
        ], 201);
    }

    public function show(Order $order)
    {
        return response()->json([
            'success' => true,
            'message' => 'Détail de la commande',
            'data' => $order
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $request->merge([
            'customer_id' => auth()->user()->userable->id
        ]);

        $validator = validator()->make($request->all(), [
            'product_id' => 'sometimes|required|exists:products,id',
            'unit_price' => 'sometimes|required|numeric',
            'total_price' => 'sometimes|required|numeric',
            'status' => 'sometimes|required',
            'customer_id' => 'sometimes|required|exists:customers,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => $validator->errors()
            ], 422);
        }

        $order->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Commande mise à jour avec succès.',
            'data' => $order->fresh()
        ]);
    }

    public function destroy(Order $order)
    {
        $order->delete();
        return response()->json([
            'success' => true,
            'message' => 'Commande supprimée avec succès.',
            'data' => null
        ]);
    }
}
