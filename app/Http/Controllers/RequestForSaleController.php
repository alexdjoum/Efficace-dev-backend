<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Land;
use App\Models\Address;
use App\Models\Location;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Models\RequestForSale;
use Illuminate\Support\Facades\DB;


class RequestForSaleController extends Controller
{   

    /**
     * @OA\Post(
     *     path="/api/request-for-sales",
     *     summary="Créer une demande de vente avec un bien immobilier ou terrain (Client)",
     *     tags={"Request For Sales"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"description","type","has_validated_contrat"},
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     example="Je souhaite vendre mon terrain de 500m² situé à Douala"
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="string",
     *                     enum={"land","villa","building"},
     *                     example="land"
     *                 ),
     *                 @OA\Property(
     *                     property="has_validated_contrat",
     *                     type="boolean",
     *                     example=true,
     *                     description="OBLIGATOIRE : Doit être à true pour accepter le contrat"
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="property_data[area]",
     *                     type="number",
     *                     format="float",
     *                     example=150.5,
     *                     description="Superficie du bien (requis si property_data)"
     *                 ),
     *                 @OA\Property(
     *                     property="property_data[numberOfRoom]",
     *                     type="integer",
     *                     example=4,
     *                     description="Nombre de chambres (optionnel)"
     *                 ),
     *                 @OA\Property(
     *                     property="property_data[numberOfToilet]",
     *                     type="integer",
     *                     example=2,
     *                     description="Nombre de toilettes (optionnel)"
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="land_data[area]",
     *                     type="number",
     *                     format="float",
     *                     example=500,
     *                     description="Superficie du terrain (requis si land_data)"
     *                 ),
     *                 @OA\Property(
     *                     property="land_data[is_fragmentable]",
     *                     type="boolean",
     *                     example=true,
     *                     description="Le terrain est-il fragmentable ? (optionnel)"
     *                 ),
     *                 @OA\Property(
     *                     property="land_data[relief]",
     *                     type="string",
     *                     example="plat",
     *                     description="Type de relief (optionnel)"
     *                 ),
     *                 @OA\Property(
     *                     property="land_data[land_title]",
     *                     type="string",
     *                     example="Titre foncier 12345",
     *                     description="Numéro du titre foncier (optionnel)"
     *                 ),
     *                 @OA\Property(
     *                     property="land_data[certificat_of_ownership]",
     *                     type="boolean",
     *                     example=true,
     *                     description="Certificat de propriété disponible ? (optionnel)"
     *                 ),
     *                 @OA\Property(
     *                     property="land_data[technical_doc]",
     *                     type="boolean",
     *                     example=true,
     *                     description="Documentation technique disponible ? (optionnel)"
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="address[country]",
     *                     type="string",
     *                     example="Cameroun",
     *                     description="Pays (optionnel)"
     *                 ),
     *                 @OA\Property(
     *                     property="address[city]",
     *                     type="string",
     *                     example="Douala",
     *                     description="Ville (optionnel)"
     *                 ),
     *                 @OA\Property(
     *                     property="address[street]",
     *                     type="string",
     *                     example="Rue de la paix",
     *                     description="Rue (optionnel)"
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="kml_file",
     *                     type="string",
     *                     format="binary",
     *                     description="Fichier KML pour la localisation (optionnel)"
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="photos[]",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary"),
     *                     description="Photos du bien (max 10, 5MB chacune)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Demande de vente créée avec succès"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation - Contrat non validé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Vous devez accepter le contrat pour créer une demande de vente")
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
            'has_validated_contrat' => 'required',
            'property_data' => 'nullable|array|required_without:land_data',
            'property_data.area' => 'required_with:property_data|numeric|min:0',
            'property_data.numberOfRoom' => 'nullable|integer|min:0',
            'property_data.numberOfToilet' => 'nullable|integer|min:0',
            
            'land_data' => 'nullable|array|required_without:property_data',
            'land_data.area' => 'required_with:land_data|numeric|min:0',
            'land_data.is_fragmentable' => 'nullable|boolean',
            'land_data.relief' => 'nullable|string|max:255',
            'land_data.land_title' => 'nullable|string|max:255',
            'land_data.certificat_of_ownership' => 'nullable|boolean',
            'land_data.technical_doc' => 'nullable|boolean',
            
            'address' => 'nullable|array',
            'address.country' => 'nullable|string|max:255',
            'address.city' => 'nullable|string|max:255',
            'address.street' => 'nullable|string|max:500',
            
            'kml_file' => 'nullable|file',
            
            'photos' => 'nullable|array|max:10',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        $hasValidatedContrat = filter_var($validated['has_validated_contrat'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($hasValidatedContrat !== true) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez accepter le contrat pour créer une demande de vente',
            ], 422);
        }

        if ($request->hasFile('kml_file')) {
            $kmlFile = $request->file('kml_file');
            
            if (strtolower($kmlFile->getClientOriginalExtension()) !== 'kml') {
                return response()->json([
                    'success' => false,
                    'message' => 'Le fichier doit être au format .kml',
                ], 422);
            }
        }

        if (isset($validated['property_data']) && isset($validated['land_data'])) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas spécifier à la fois property_data et land_data',
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            $saleableType = null;
            $saleableId = null;
            $createdItem = null;

            $location = \App\Models\Location::create([
                'coordinate_link' => null,
            ]);

            if ($request->hasFile('kml_file')) {
                $kmlFile = $request->file('kml_file');
                
                $tempFilePath = $this->simplifyAndSaveKml(
                    $kmlFile->getPathname(),
                    $kmlFile->getClientOriginalName()
                );

                if (file_exists($tempFilePath)) {
                    $media = $location
                        ->addMedia($tempFilePath)
                        ->usingFileName($kmlFile->getClientOriginalName())
                        ->toMediaCollection('kml');

                    $location->update([
                        'coordinate_link' => $media->getUrl(),
                    ]);

                    @unlink($tempFilePath);
                }
            }

            if (isset($validated['address'])) {
                $address = \App\Models\Address::create([
                    'country' => $validated['address']['country'] ?? null,
                    'city' => $validated['address']['city'] ?? null,
                    'street' => $validated['address']['street'] ?? null,
                ]);

                $location->address()->save($address);
            }

            if (isset($validated['property_data'])) {
                $property = \App\Models\Property::create([
                    'area' => $validated['property_data']['area'],
                    'numberOfRoom' => $validated['property_data']['numberOfRoom'] ?? null,
                    'numberOfToilet' => $validated['property_data']['numberOfToilet'] ?? null,
                    'location_id' => $location->id,
                ]);
                
                if ($request->hasFile('photos')) {
                    foreach ($request->file('photos') as $photo) {
                        $property->addMedia($photo)->toMediaCollection('property');
                    }
                }
                
                $saleableType = 'App\\Models\\Property';
                $saleableId = $property->id;
                $createdItem = $property->load('media', 'location.address');
            }
            
            if (isset($validated['land_data'])) {
                $land = \App\Models\Land::create([
                    'area' => $validated['land_data']['area'],
                    'is_fragmentable' => $validated['land_data']['is_fragmentable'] ?? false,
                    'relief' => $validated['land_data']['relief'] ?? null,
                    'description' => $validated['description'],
                    'land_title' => $validated['land_data']['land_title'] ?? null,
                    'certificat_of_ownership' => $validated['land_data']['certificat_of_ownership'] ?? false,
                    'technical_doc' => $validated['land_data']['technical_doc'] ?? false,
                    'location_id' => $location->id,
                ]);
                
                if ($request->hasFile('photos')) {
                    foreach ($request->file('photos') as $photo) {
                        $land->addMedia($photo)->toMediaCollection('land');
                    }
                }
                
                $saleableType = 'App\\Models\\Land';
                $saleableId = $land->id;
                $createdItem = $land->load('media', 'location.address');
            }

            $requestForSale = RequestForSale::create([
                'user_id' => $user->id,
                'saleable_type' => $saleableType,
                'saleable_id' => $saleableId,
                'description' => $validated['description'],
                'type' => $validated['type'],
                'status' => 'pending',
                'has_validated_contrat' => true,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Demande de vente créée avec succès',
                'data' => [
                    'request' => $requestForSale,
                    'created_item' => [
                        'type' => class_basename($saleableType),
                        'data' => $createdItem,
                    ]
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Erreur création request for sale', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la demande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function simplifyAndSaveKml(string $originalPath, string $originalFileName): string
    {
        $xml = @simplexml_load_file($originalPath);

        if (!$xml) {
            throw new \Exception("Impossible de charger le KML. Vérifiez le format XML.");
        }
        
        $tempFilePath = tempnam(sys_get_temp_dir(), 'kml_min_');
        
        if ($tempFilePath === false) {
            throw new \Exception("Impossible de créer un fichier temporaire pour le KML simplifié.");
        }

        if (isset($xml->Document->Schema)) {
            unset($xml->Document->Schema);
        }

        $placemarks = $xml->xpath('//Placemark');
        foreach ($placemarks as $placemark) {
            if (isset($placemark->ExtendedData)) {
                unset($placemark->ExtendedData);
            }
        }

        if (!$xml->asXML($tempFilePath)) {
            throw new \Exception("Impossible de sauvegarder le KML simplifié.");
        }

        return $tempFilePath;
    }

    /**
     * @OA\Get(
     *     path="/api/request-for-sales/my-requests",
     *     summary="Voir mes demandes de vente (Client)",
     *     tags={"Request For Sales"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Liste de mes demandes")
     * )
     */
    public function myRequests()
    {
        $user = auth()->user();

        $requests = RequestForSale::where('user_id', $user->id)
            ->with('saleable')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requests->map(function ($req) {
                return [
                    'id' => $req->id,
                    'description' => $req->description,
                    'type' => $req->type,
                    'status' => $req->status,
                    'has_validated_contrat' => $req->has_validated_contrat,
                    'saleable' => $req->saleable ? [
                        'id' => $req->saleable->id,
                        'type' => class_basename($req->saleable_type),
                        'data' => $req->saleable,
                    ] : null,
                    'created_at' => $req->created_at,
                ];
            })
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
     *             @OA\Property(property="has_validated_contrat", type="boolean", nullable=true),
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
            'has_validated_contrat' => 'nullable|boolean',
            'perPage' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = RequestForSale::with('user.contact', 'saleable.photos');

        $perPage = $validated['perPage'] ?? 15;
        $page = $validated['page'] ?? 1;

        $query = RequestForSale::with('user.contact', 'saleable');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['has_validated_contrat'])) {
            $query->where('has_validated_contrat', $validated['has_validated_contrat']);
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
                    'has_validated_contrat' => $req->has_validated_contrat,
                    'saleable' => $req->saleable ? [
                        'id' => $req->saleable->id,
                        'type' => class_basename($req->saleable_type),
                        'photos' => $req->saleable->photos,
                        'data' => $req->saleable,
                    ] : null,
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