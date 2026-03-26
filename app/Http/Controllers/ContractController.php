<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContractController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/admin/contracts",
     *     summary="Uploader un contrat PDF (Admin uniquement)",
     *     tags={"Contracts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"title","type","contract_file"},
     *                 @OA\Property(
     *                     property="title",
     *                     type="string",
     *                     example="Contrat de vente standard"
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="string",
     *                     enum={"inscription","request_for_sales"},
     *                     example="request_for_sales"
     *                 ),
     *                 @OA\Property(
     *                     property="contract_file",
     *                     type="string",
     *                     format="binary",
     *                     description="Fichier PDF du contrat"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Contrat uploadé avec succès"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin', 'api')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux administrateurs',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:inscription,request_for_sales',
            'contract_file' => 'required|file|mimes:pdf|max:10240', 
        ]);

        if ($request->hasFile('contract_file')) {
            $file = $request->file('contract_file');
            
            $fileName = time() . '_' . $file->getClientOriginalName();
            
            $path = $file->storeAs('contracts', $fileName, 'public');
            
            $contract = Contract::create([
                'title' => $validated['title'],
                'type' => $validated['type'],
                'file_path' => $path,
                'file_url' => asset('storage/' . $path),
                'uploaded_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contrat créé avec succès',
                'data' => $contract
            ], 201);
        }

        return response()->json([
            'success' => false,
            'message' => 'Aucun fichier uploadé',
        ], 422);
    }

    /**
     * @OA\Post(
     *     path="/api/contracts",
     *     summary="Récupérer le contrat selon le type",
     *     tags={"Contracts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type"},
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 enum={"inscription","request_for_sales"},
     *                 example="request_for_sales",
     *                 description="Type de contrat à récupérer"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contrat récupéré avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Contrat de vente standard"),
     *                 @OA\Property(property="type", type="string", example="request_for_sales"),
     *                 @OA\Property(property="file_url", type="string", example="http://example.com/storage/contracts/contract.pdf"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Aucun contrat trouvé pour ce type",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Aucun contrat trouvé pour ce type")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:inscription,request_for_sales',
        ]);

        $contract = Contract::where('type', $validated['type'])
            ->with('uploader.contact')
            ->latest()
            ->first();

        if (!$contract) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun contrat trouvé pour ce type',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $contract->id,
                'title' => $contract->title,
                'type' => $contract->type,
                'file_url' => $contract->file_url,
                'uploaded_by' => [
                    'id' => $contract->uploader?->id,
                    'firstName' => $contract->uploader?->contact?->firstName,
                    'lastName' => $contract->uploader?->contact?->lastName,
                ],
                'created_at' => $contract->created_at,
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/contracts/{id}",
     *     summary="Récupérer un contrat spécifique",
     *     tags={"Contracts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Détails du contrat")
     * )
     */
    public function show($id)
    {
        $contract = Contract::with('uploader.contact')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $contract->id,
                'title' => $contract->title,
                'type' => $contract->type,
                'file_url' => $contract->file_url,
                'file_path' => $contract->file_path,
                'uploaded_by' => [
                    'id' => $contract->uploader?->id,
                    'firstName' => $contract->uploader?->contact?->firstName,
                    'lastName' => $contract->uploader?->contact?->lastName,
                ],
                'created_at' => $contract->created_at,
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/contracts/{id}/download",
     *     summary="Télécharger un contrat",
     *     tags={"Contracts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Téléchargement du fichier PDF")
     * )
     */
    public function download($id)
    {
        $contract = Contract::findOrFail($id);

        if (!Storage::disk('public')->exists($contract->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Fichier introuvable',
            ], 404);
        }

        return Storage::disk('public')->download($contract->file_path, $contract->title . '.pdf');
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/contracts/{id}",
     *     summary="Supprimer un contrat (Admin uniquement)",
     *     tags={"Contracts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Contrat supprimé")
     * )
     */
    public function destroy($id)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin', 'api')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux administrateurs',
            ], 403);
        }

        $contract = Contract::findOrFail($id);

        if (Storage::disk('public')->exists($contract->file_path)) {
            Storage::disk('public')->delete($contract->file_path);
        }

        $contract->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contrat supprimé',
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/admin/contracts",
     *     summary="Lister tous les contrats (Admin uniquement)",
     *     tags={"Contracts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"inscription","request_for_sales"}),
     *         description="Filtrer par type (optionnel)"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des contrats",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Contrat de vente standard"),
     *                     @OA\Property(property="type", type="string", example="request_for_sales"),
     *                     @OA\Property(property="file_url", type="string", example="http://example.com/storage/contracts/contract.pdf"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function listAll(Request $request)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin', 'api')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux administrateurs',
            ], 403);
        }

        $query = Contract::with('uploader.contact');

        if ($request->has('type')) {
            $request->validate([
                'type' => 'in:inscription,request_for_sales',
            ]);
            
            $query->where('type', $request->type);
        }

        $contracts = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $contracts->map(function ($contract) {
                return [
                    'id' => $contract->id,
                    'title' => $contract->title,
                    'type' => $contract->type,
                    'file_url' => $contract->file_url,
                    'uploaded_by' => [
                        'id' => $contract->uploader?->id,
                        'firstName' => $contract->uploader?->contact?->firstName,
                        'lastName' => $contract->uploader?->contact?->lastName,
                    ],
                    'created_at' => $contract->created_at,
                ];
            })
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/contracts/{id}",
     *     summary="Récupérer un contrat spécifique (Admin)",
     *     tags={"Contracts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails du contrat",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(property="file_url", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function showForAdmin($id)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin', 'api')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux administrateurs',
            ], 403);
        }

        $contract = Contract::with('uploader.contact')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $contract->id,
                'title' => $contract->title,
                'type' => $contract->type,
                'file_url' => $contract->file_url,
                'file_path' => $contract->file_path,
                'uploaded_by' => [
                    'id' => $contract->uploader?->id,
                    'firstName' => $contract->uploader?->contact?->firstName,
                    'lastName' => $contract->uploader?->contact?->lastName,
                ],
                'created_at' => $contract->created_at,
            ]
        ]);
    }
}