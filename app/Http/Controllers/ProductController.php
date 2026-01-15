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
    $products = Product::with(['productable', 'proposedProducts.productable.location', 'proposedProducts.productable']) 
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($product) {
            $productable = $product->productable;
            
            if ($productable) {
                if ($product->productable_type === 'App\\Models\\Land') {
                    $productable->load([
                        'fragments',
                        'videoLands',
                        'location.address',
                        'location.media',
                        'proposedSites.proposable' => function ($query) {
                            $query->with(['location.address', 'location.media'])
                                ->without(['proposed_sites']);
                        }
                    ]);
                    
                    $proposedProducts = $productable->proposedSites->map(function ($proposedSite) {
                        return Product::where('productable_type', $proposedSite->proposable_type)
                            ->where('productable_id', $proposedSite->proposable_id)
                            ->with([
                                'productable.location.address',
                                'productable.location.media',
                                'productable.accommodations',
                                'productable.retail_spaces',
                                'productable.partOfBuildings'
                            ])
                            ->first();
                    })->filter(); 
                    
                    $productable->proposed_sites = $proposedProducts;
                    unset($productable->proposedSites); 
                    
                } elseif ($product->productable_type === 'App\\Models\\Property') {
                    $productable->load([
                        'accommodations',
                        'retail_spaces',
                        'partOfBuildings.typeOfPartOfTheBuilding',
                        'buildingFinance.buildingInvestment',
                        'operatingRatios',
                        'location.address',
                        'location.media',
                        'proposedSites.proposable' => function ($query) {
                            $query->with([
                                'location.address',
                                'location.media',
                                'fragments',
                                'videoLands'
                            ])
                            ->without(['proposed_sites']);
                        }
                    ]);

                    if ($productable->type === 'building' && $productable->partOfBuildings->isNotEmpty()) {
                        
                        $typeCounts = $productable->partOfBuildings
                            ->filter(function ($part) {
                                return $part->typeOfPartOfTheBuilding !== null;
                            })
                            ->groupBy('typeOfPartOfTheBuilding.id')
                            ->map(function ($parts) {
                                $firstPart = $parts->first();
                                return [
                                    'type_id' => $firstPart->typeOfPartOfTheBuilding->id,
                                    'type_name' => $firstPart->typeOfPartOfTheBuilding->name,
                                    'count' => $parts->count()
                                ];
                            })
                            ->values();
                        
                        $productable->overall_program = $typeCounts;
                        
                        if ($productable->buildingFinance) {
                            
                            $investmentCost = round(
                                (float) $productable->buildingFinance->project_study 
                                + (float) $productable->buildingFinance->building_permit 
                                + (float) $productable->buildingFinance->structural_work 
                                + (float) $productable->buildingFinance->finishing 
                                + (float) $productable->buildingFinance->equipments 
                                + (float) $productable->buildingFinance->cost_of_land,
                                2
                            );
                            
                            $productable->buildingFinance->total_building_finance = $investmentCost;
                            
                            $growthInMarketValue = 0;
                            $annualExpense = 0;
                            
                            if ($productable->buildingFinance->buildingInvestment) {
                                $investment = $productable->buildingFinance->buildingInvestment;
                                $growthInMarketValue = round((float) $investment->growth_in_market_value, 2);
                                $annualExpense = round((float) $investment->annual_expense, 2);
                            }
                            
                            $mountIncome = 0;
                            
                            foreach ($productable->operatingRatios as $ratio) {
                                $typeCount = $typeCounts->firstWhere('type_name', $ratio->type);
                                $count = $typeCount ? $typeCount['count'] : 0;
                                
                                $mountIncome += (float) $ratio->montant * $count;
                            }
                            
                            $mountIncome = round($mountIncome, 2);
                            $percentIncome = $investmentCost > 0 ? round(($mountIncome * 100) / $investmentCost, 2) : 0;
                            
                            $mountMargin = round($mountIncome - $annualExpense, 2);
                            $percentMargin = $investmentCost > 0 ? round(($mountMargin * 100) / $investmentCost, 2) : 0;
                            
                            $annualInvestmentGrowth = round($percentMargin + $growthInMarketValue, 2);
                            
                            $returnOnInvestmentPeriod = ($percentMargin > 0) 
                                ? round(100 / $percentMargin, 2) 
                                : null;
                            
                            $productable->investment = [
                                'investment_cost' => $investmentCost,
                                'growth_in_market_value' => $growthInMarketValue,
                                'total_income' => [
                                    'mount_income' => $mountIncome,
                                    'percent' => $percentIncome
                                ],
                                'annual_expense' => $annualExpense,
                                'annual_net_operating_margin' => [
                                    'mount_margin' => $mountMargin,
                                    'percent_margin' => $percentMargin
                                ],
                                'annual_investment_growth' => $annualInvestmentGrowth,
                                'return_on_investment_period' => $returnOnInvestmentPeriod
                            ];
                            
                            $productable->buildingFinance->makeHidden(['created_at', 'updated_at']);
                        }
                        
                        $productable->partOfBuildings->each(function ($part) {
                            $part->makeHidden(['media', 'created_at', 'updated_at']);
                            
                            if ($part->typeOfPartOfTheBuilding) {
                                $part->typeOfPartOfTheBuilding->makeHidden(['created_at', 'updated_at']);
                            }
                        });
                    } else {
                        $productable->overall_program = [];
                    }

                    if ($productable->type === 'building') {
                        $productable->makeHidden(['bedrooms', 'bathrooms', 'number_of_salons']);
                    } else {
                        $productable->makeHidden(['number_of_appartements', 'part_of_buildings', 'building_finance']);
                    }
                    
                    $productable->makeHidden(['operating_ratios', 'created_at', 'updated_at']);

                    $proposedProducts = $productable->proposedSites->map(function ($proposedSite) {
                        return Product::where('productable_type', $proposedSite->proposable_type)
                            ->where('productable_id', $proposedSite->proposable_id)
                            ->with([
                                'productable.location.address',
                                'productable.location.media',
                                'productable.fragments',
                                'productable.videoLands'
                            ])
                            ->first();
                    })->filter(); 
                    
                    $productable->proposed_sites = $proposedProducts;
                    unset($productable->proposedSites); 
                }
            }
            
            if ($product->proposedProducts->isNotEmpty()) {
                $product->proposed_products = $product->proposedProducts->map(function ($proposedProduct) {
                    
                    if ($proposedProduct->productable_type === 'App\\Models\\Land') {
                        $proposedProduct->productable->load([
                            'location.address',
                            'fragments',
                            'videoLands',
                        ]);
                        
                        if ($proposedProduct->productable->location) {
                            $proposedProduct->productable->location->makeHidden(['media', 'created_at', 'updated_at']);
                        }
                        
                    } elseif ($proposedProduct->productable_type === 'App\\Models\\Property') {
                        $proposedProduct->productable->load([
                            'partOfBuildings.typeOfPartOfTheBuilding',
                            'buildingFinance',
                            'location.address',
                        ]);
                        
                        if ($proposedProduct->productable->location) {
                            $proposedProduct->productable->location->makeHidden(['media', 'created_at', 'updated_at']);
                        }
                        
                        $proposedProduct->productable->makeHidden([
                            'accommodations',
                            'retail_spaces',
                            'created_at',
                            'updated_at'
                        ]);
                    }
                    
                    return [
                        'id' => $proposedProduct->id,
                        'reference' => $proposedProduct->reference,
                        'description' => $proposedProduct->description,
                        'for_sale' => $proposedProduct->for_sale,
                        'for_rent' => $proposedProduct->for_rent,
                        'unit_price' => $proposedProduct->unit_price,
                        'total_price' => $proposedProduct->total_price,
                        'status' => $proposedProduct->status,
                        'productable_type' => $proposedProduct->productable_type,
                        'productable' => $proposedProduct->productable
                    ];
                });
            } else {
                $product->proposed_products = [];
            }
            
            $product->makeHidden(['proposedProducts']);
            
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
