<?php

namespace App\Http\Controllers;

use App\Models\RequestForSale;
use Illuminate\Http\Request;

class RequestForSaleController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/request-for-sales",
     *     summary="Créer une demande de vente (Client)",
     *     tags={"Request For Sales"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"description","type"},
     *             @OA\Property(property="description", type="string", example="Je souhaite vendre mon terrain de 500m²"),
     *             @OA\Property(property="type", type="string", enum={"land","villa","building"}, example="land")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Demande créée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Demande de vente créée avec succès")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'description' => 'required|string|max:5000',
            'type' => 'required|in:land,villa,building',
        ]);

        $requestForSale = RequestForSale::create([
            'user_id' => $user->id,
            'description' => $validated['description'],
            'type' => $validated['type'],
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande de vente créée avec succès',
            'data' => $requestForSale
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/request-for-sales/my-requests",
     *     summary="Voir mes demandes de vente (Client)",
     *     tags={"Request For Sales"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste de mes demandes"
     *     )
     * )
     */
    public function myRequests()
    {
        $user = auth()->user();

        $requests = RequestForSale::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/request-for-sales",
     *     summary="Lister toutes les demandes avec pagination (Admin)",
     *     tags={"Request For Sales"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", nullable=true, enum={"pending","accepted","rejected"}),
     *             @OA\Property(property="type", type="string", nullable=true, enum={"land","villa","building"}),
     *             @OA\Property(property="perPage", type="integer", example=15),
     *             @OA\Property(property="page", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Liste paginée des demandes")
     * )
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin', 'api') && !$user->hasRole('validator', 'api')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux admins et validators',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'nullable|in:pending,accepted,rejected',
            'type' => 'nullable|in:land,villa,building',
            'perPage' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $perPage = $validated['perPage'] ?? 15;
        $page = $validated['page'] ?? 1;

        $query = RequestForSale::with('user.contact');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        $requests = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $requests->getCollection()->map(function ($req) {
                return [
                    'id' => $req->id,
                    'user' => [
                        'id' => $req->user?->id,
                        'firstName' => $req->user?->contact?->firstName,
                        'lastName' => $req->user?->contact?->lastName,
                        'email' => $req->user?->contact?->email,
                    ],
                    'description' => $req->description,
                    'type' => $req->type,
                    'status' => $req->status,
                    'created_at' => $req->created_at,
                ];
            }),
            'pagination' => [
                'total' => $requests->total(),
                'perPage' => $requests->perPage(),
                'currentPage' => $requests->currentPage(),
                'lastPage' => $requests->lastPage(),
                'from' => $requests->firstItem(),
                'to' => $requests->lastItem(),
            ]
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/request-for-sales/{id}/status",
     *     summary="Modifier le statut d'une demande (Admin)",
     *     tags={"Request For Sales"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"pending","accepted","rejected"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Statut modifié")
     * )
     */
    public function updateStatus(Request $request, $id)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin', 'api') && !$user->hasRole('validator', 'api')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux admins et validators',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,accepted,rejected',
        ]);

        $requestForSale = RequestForSale::findOrFail($id);

        $requestForSale->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Statut modifié avec succès',
            'data' => $requestForSale
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/request-for-sales/{id}",
     *     summary="Supprimer ma demande (Client)",
     *     tags={"Request For Sales"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Demande supprimée")
     * )
     */
    public function destroy($id)
    {
        $user = auth()->user();

        $requestForSale = RequestForSale::findOrFail($id);

        // ✅ Seul le propriétaire ou un admin peut supprimer
        if ($requestForSale->user_id !== $user->id && !$user->hasRole('admin', 'api')) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée',
            ], 403);
        }

        $requestForSale->delete();

        return response()->json([
            'success' => true,
            'message' => 'Demande supprimée',
        ]);
    }
}