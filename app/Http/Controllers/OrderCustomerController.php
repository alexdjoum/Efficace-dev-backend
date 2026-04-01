<?php

namespace App\Http\Controllers;

use App\Models\OrderCustomer;
use Illuminate\Http\Request;

class OrderCustomerController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/order-customers",
     *     summary="Créer une commande client",
     *     tags={"Order Customers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone_number","budget","localization","type"},
     *             @OA\Property(property="phone_number", type="string", example="+237670000000"),
     *             @OA\Property(property="budget", type="number", example=50000000),
     *             @OA\Property(property="localization", type="string", example="Douala, Akwa"),
     *             @OA\Property(property="land_area", type="number", example=5000),
     *             @OA\Property(property="description", type="string", example="Je recherche un terrain"),
     *             @OA\Property(property="type", type="string", enum={"land","building","city"}, example="land"),
     *             @OA\Property(property="purchase_time", type="string", example="6 mois"),
     *             @OA\Property(property="building_type", type="string", enum={"commercial","office","hotel","furnished_apartment","apartment_rental"}),
     *             @OA\Property(property="number_of_apartments", type="integer", example=20),
     *             @OA\Property(property="function", type="string", enum={"ressort","social_housing","commercial_housing","business_district","residential_area","gate_community"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="Commande créée avec succès")
     * )
     */
    public function store(Request $request)
    {
        $rules = [
            'phone_number' => 'required|string|max:255',
            'budget' => 'required|numeric|min:0',
            'localization' => 'required|string|max:255',
            'land_area' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'type' => 'required|in:land,building,city',
        ];

        if ($request->type === 'land') {
            $rules['purchase_time'] = 'required|string|max:255';
        } elseif ($request->type === 'building') {
            $rules['building_type'] = 'required|in:commercial,office,hotel,furnished_apartment,apartment_rental';
            $rules['number_of_apartments'] = 'required|integer|min:1';
        } elseif ($request->type === 'city') {
            $rules['function'] = 'required|in:ressort,social_housing,commercial_housing,business_district,residential_area,gate_community';
        }

        $validated = $request->validate($rules);

        $validated['user_id'] = auth()->id();

        $orderCustomer = OrderCustomer::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Commande créée avec succès',
            'data' => $orderCustomer
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/order-customers",
     *     summary="Liste des commandes (Admin voit tout, Client voit les siennes)",
     *     tags={"Order Customers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Liste des commandes")
     * )
     */
    public function index()
    {
        $user = auth()->user();

        if ($user->hasRole('admin', 'api')) {
            $orders = OrderCustomer::with('user')->orderBy('created_at', 'desc')->get();
        } else {
            $orders = OrderCustomer::where('user_id', $user->id)->orderBy('created_at', 'desc')->get();
        }

        return response()->json([
            'success' => true,
            'message' => 'Liste des commandes',
            'data' => $orders
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/order-customers/my-orders",
     *     summary="Mes commandes (Client)",
     *     tags={"Order Customers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Liste de mes commandes")
     * )
     */
    public function myOrders()
    {
        $orders = OrderCustomer::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Mes commandes',
            'data' => $orders
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/order-customers/{id}",
     *     summary="Détails d'une commande",
     *     tags={"Order Customers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Détails de la commande")
     * )
     */
    public function show($id)
    {
        $user = auth()->user();
        $order = OrderCustomer::with('user')->findOrFail($id);

        if (!$user->hasRole('admin', 'api') && $order->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à voir cette commande'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Détails de la commande',
            'data' => $order
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/order-customers/{id}",
     *     summary="Supprimer une commande (Admin uniquement)",
     *     tags={"Order Customers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Commande supprimée avec succès")
     * )
     */
    public function destroy($id)
    {
        $user = auth()->user();
        if (!$user->hasRole('admin', 'api')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux administrateurs'
            ], 403);
        }

        $order = OrderCustomer::findOrFail($id);
        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Commande supprimée avec succès'
        ]);
    }
}