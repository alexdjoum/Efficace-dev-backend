<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
{
    $products = Product::with('productable')->get()->map(function ($product) {
        $productable = $product->productable;
        
        if ($productable) {
            if ($product->productable_type === 'App\\Models\\Land') {
                // Charger TOUTES les relations du Land
                $productable->load([
                    'fragments:id,land_id,area',
                    'videoLands:id,land_id,videoLink',
                    'location:id,coordinate_link',
                    'location.address:id,addressable_id,addressable_type,street,city,country',
                    'proposedSites' => function ($query) {
                        $query->with(['proposable' => function ($q) {
                            $q->select('id', 'title', 'type', 'bedrooms', 'bathrooms', 'estimated_payment');
                        }]);
                    }
                ]);
                
            } elseif ($product->productable_type === 'App\\Models\\Property') {
                // Charger TOUTES les relations de la Property
                $productable->load([
                    'accommodations',
                    'retail_spaces',
                    'location:id,coordinate_link',
                    'location.address:id,addressable_id,addressable_type,street,city,country',
                    'proposedSites' => function ($query) {
                        $query->with(['proposable' => function ($q) {
                            $q->select('id', 'area', 'relief', 'land_title', 'description');
                        }]);
                    }
                ]);
            }
        }
        
        return $product;
    });

    return response()->json([
        'success' => true,
        'message' => 'Liste des produits',
        'data' => $products
    ]);
}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, ProductService $productService)
{
    // ✅ FORCER LE PARSING DU JSON
    $data = $request->all();
    
    // Si $data est vide, parser manuellement le contenu JSON
    if (empty($data) && $request->getContent()) {
        $data = json_decode($request->getContent(), true);
    }
    
    // ✅ Vérifier que $data n'est pas null
    if ($data === null || !is_array($data)) {
        return response()->json([
            'success' => false,
            'message' => 'Données invalides ou requête vide.',
            'error' => 'Le corps de la requête doit contenir des données JSON valides.'
        ], 400);
    }
    
    // Valider les données parsées
    $validator = validator()->make($data, [
        'type' => 'required|in:land,property,accommodation,virtual,retail_space',
        'productable_id' => 'required|integer',
        'description' => 'required|string',
        'for_sale' => 'required|boolean',
        'for_rent' => 'required|boolean',
        'unit_price' => 'required|numeric|min:0',
        'total_price' => 'required|numeric|min:0',
        'status' => 'required|string',
        'publish' => 'required|boolean',
        'published_at' => 'nullable|date',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Erreurs de validation.',
            'data' => ['errors' => $validator->errors()]
        ], 422);
    }

    try {
        $product = DB::transaction(function () use ($data, $productService) {
            return $productService->create($data);
        });

        return response()->json([
            'success' => true,
            'message' => 'Produit créé avec succès',
            'data' => $product
        ], 201);
    } catch (\Exception $e) {
        \Log::error('Error creating product', ['error' => $e->getMessage()]);
        
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la création du produit',
            'error' => config('app.debug') ? $e->getMessage() : 'Erreur serveur'
        ], 500);
    }
}

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'message' => 'Details du produit',
            'data' => $product
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product, ProductService $productService)
    {
        $product = DB::transaction(function () use ($request, $product, $productService) {
            return $productService->update($product, $request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Produit mis à jour avec succès',
            'data' => $product
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json([
            'success' => true,
            'message' => 'Produit supprimé avec succès',
            'data' => null
        ]);
    }
}
