<?php

namespace App\Http\Controllers;

use App\Models\Lot;
use Illuminate\Http\Request;

class LotController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/lots",
     *     summary="Lister les lots enfants d'un lot principal",
     *     tags={"Lots"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 example="engineer",
     *                 description="Nom du lot principal"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des lots enfants",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="structure"),
     *                     @OA\Property(property="role", type="string", example="child")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lot principal non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Lot principal non trouvé")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Le champ name est requis")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|exists:lots,name',
        ]);

        $mainLot = Lot::where('name', $validated['name'])
            ->where('role', 'main')
            ->first();

        if (!$mainLot) {
            return response()->json([
                'success' => false,
                'message' => 'Lot principal non trouvé',
            ], 404);
        }

        $children = Lot::where('main_id', $mainLot->id)
            ->select('id', 'name', 'role')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $children
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/lots/create",
     *     summary="Créer un lot",
     *     tags={"Lots"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","role"},
     *             @OA\Property(property="name", type="string", example="structure"),
     *             @OA\Property(property="role", type="string", enum={"main", "child"}, example="child"),
     *             @OA\Property(property="main_id", type="integer", nullable=true, example=1, description="Requis si role=child")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Lot créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lot créé avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=14),
     *                 @OA\Property(property="name", type="string", example="structure"),
     *                 @OA\Property(property="role", type="string", example="child"),
     *                 @OA\Property(property="main_id", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Le main_id est requis pour un lot child")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|in:main,child',
            'main_id' => 'nullable|exists:lots,id',
        ]);

        if ($validated['role'] === 'child' && empty($validated['main_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Le main_id est requis pour un lot child',
            ], 422);
        }

        if ($validated['role'] === 'main') {
            $validated['main_id'] = null;
        }

        $lot = Lot::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Lot créé avec succès',
            'data' => $lot
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/lots/main",
     *     summary="Lister uniquement les lots principaux",
     *     tags={"Lots"},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des lots principaux",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="engineer"),
     *                     @OA\Property(property="role", type="string", example="main")
     *                 )
     *             )
     *         )
     *     )
     * )
     */ 
    public function mainLots()
    {
        $lots = Lot::where('role', 'main')
            ->select('id', 'name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $lots
        ]);
    }
}