<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ProductResource;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::with([
            'productable', // Charger le productable de base
            'proposedProducts.productable',
        ])
        ->orderBy('created_at', 'desc')
        ->get();

        // Charger les relations conditionnellement après
        $products->each(function ($product) {
            if ($product->productable_type === 'App\\Models\\Land') {
                $product->productable->load([
                    'location.address',
                    'fragments',
                    'videoLands',
                ]);
            } elseif ($product->productable_type === 'App\\Models\\Property') {
                $product->productable->load([
                    'location.address',
                    'partOfBuildings.typeOfPartOfTheBuilding',
                    'buildingFinances.buildingInvestment',
                ]);
            }

            // Même chose pour les proposed products
            $product->proposedProducts->each(function ($proposedProduct) {
                if ($proposedProduct->productable_type === 'App\\Models\\Land') {
                    $proposedProduct->productable->load([
                        'location.address',
                        'fragments',
                        'videoLands',
                    ]);
                } elseif ($proposedProduct->productable_type === 'App\\Models\\Property') {
                    $proposedProduct->productable->load([
                        'location.address',
                        'partOfBuildings.typeOfPartOfTheBuilding',
                        'buildingFinances',
                    ]);
                }
            });
        });

        return response()->json([
            'success' => true,
            'message' => __('messages.product_list'),
            'data' => ProductResource::collection($products)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, ProductService $productService)
    {
        $data = $request->all();
        
        if (empty($data) && $request->getContent()) {
            $data = json_decode($request->getContent(), true);
        }
        
        if ($data === null || !is_array($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides ou requête vide.',
                'error' => 'Le corps de la requête doit contenir des données JSON valides.'
            ], 400);
        }
        
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
            'proposed_product_ids' => 'nullable|array', 
            'proposed_product_ids.*' => 'integer|exists:products,id',
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
