<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ProductResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    const CACHE_TAG = 'products';
    const CACHE_TTL = 3600*5; 
    public function index(Request $request)
    {
        $products = $this->loadProducts();

        return response()->json([
            'success' => true,
            'message' => __('messages.product_list'),
            'data' => ProductResource::collection($products),
            'cached' => false,
            'cache_expires_in' => null,
        ]);
    }

    protected function loadProducts()
    {
        $products = Product::with([
            'productable',
            'proposedProducts.productable',
        ])
        ->orderBy('created_at', 'desc')
        ->get()
        ->each(function ($product) {
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
                    'buildingFinances',
                    'buildingInvestment',
                ]);
                
                $this->calculateInvestment($product->productable);
            }

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
                        'buildingInvestment',
                    ]);
                    
                    $this->calculateInvestment($proposedProduct->productable);
                }
            });
        });
        
        return $products; 
    }

    protected function calculateInvestment($property)
    {
        
        if ($property->buildingInvestment) {
            
            $mediumFinance = collect($property->buildingFinances ?? [])->firstWhere('type_of_standing', 'medium');
            
            if ($mediumFinance) {
                $investmentCost = round(
                    (float) $mediumFinance->project_study 
                    + (float) $mediumFinance->building_permit 
                    + (float) $mediumFinance->structural_work 
                    + (float) $mediumFinance->finishing 
                    + (float) $mediumFinance->equipments 
                    + (float) $mediumFinance->cost_of_land,
                    2
                );
                
                $investment = $property->buildingInvestment;
                $growthInMarketValue = round((float) $investment->growth_in_market_value, 2);
                $annualExpense = round((float) $investment->annual_expense, 2);
                $mountIncome = round((float) $investment->mount_income, 2);
                
                $percentIncome = $investmentCost > 0 ? round(($mountIncome * 100) / $investmentCost, 2) : 0;
                $mountMargin = round($mountIncome - $annualExpense, 2);
                $percentMargin = $investmentCost > 0 ? round(($mountMargin * 100) / $investmentCost, 2) : 0;
                $annualInvestmentGrowth = round($percentMargin + $growthInMarketValue, 2);
                $returnOnInvestmentPeriod = ($percentMargin > 0) ? round(100 / $percentMargin, 2) : null;
                
                $property->investment = [
                    'investment_cost' => $investmentCost,
                    'growth_in_market_value' => $growthInMarketValue,
                    'total_income' => [
                        'mount_income' => $mountIncome,
                        'percent' => $percentIncome,
                    ],
                    'annual_expense' => $annualExpense,
                    'annual_net_operating_margin' => [
                        'mount_margin' => $mountMargin,
                        'percent_margin' => $percentMargin,
                    ],
                    'annual_investment_growth' => $annualInvestmentGrowth,
                    'return_on_investment_period' => $returnOnInvestmentPeriod,
                ];
                
                \Log::info('Investment calculé et assigné', ['investment' => $property->investment]);
            }
        }
    }

    protected function clearProductsCache()
    {
        Cache::tags([self::CACHE_TAG])->flush();
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

    public function cacheStats()
    {
        $stats = [
            'driver' => config('cache.default'),
            'products_cache_fr_exists' => Cache::tags([self::CACHE_TAG])->has('products_list_fr'),
            'products_cache_en_exists' => Cache::tags([self::CACHE_TAG])->has('products_list_en'),
            'redis_info' => [],
        ];

        try {
            $redis = Redis::connection('cache'); 
            $info = $redis->info();
            $stats['redis_info'] = [
                'used_memory' => $info['used_memory_human'] ?? 'N/A',
                'connected_clients' => $info['connected_clients'] ?? 'N/A',
                'total_commands_processed' => $info['total_commands_processed'] ?? 'N/A',
            ];
        } catch (\Exception $e) {
            $stats['redis_error'] = $e->getMessage();
        }

        return response()->json($stats);
    }

    /**
     * @OA\Post(
     *     path="/api/products/payment-plan",
     *     summary="Calculer le plan de paiement et l'investissement pour un produit",
     *     tags={"Products"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id","purchase_duration","standing"},
     *             @OA\Property(property="product_id", type="integer", example=41),
     *             @OA\Property(property="purchase_duration", type="integer", example=24),
     *             @OA\Property(property="standing", type="string", enum={"high","medium","low"}, example="medium")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Plan de paiement et investissement calculés",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Plan de paiement calculé"),
     *             @OA\Property(
     *                 property="payment_plan",
     *                 type="object",
     *                 @OA\Property(property="total_cost", type="number", example=217900000),
     *                 @OA\Property(property="duration_months", type="integer", example=24),
     *                 @OA\Property(property="weekly", type="number", example=2273958.33),
     *                 @OA\Property(property="monthly", type="number", example=9095833.33),
     *                 @OA\Property(property="quarterly", type="number", example=27287500),
     *                 @OA\Property(property="semester", type="number", example=54575000),
     *                 @OA\Property(property="annual", type="number", example=109150000)
     *             ),
     *             @OA\Property(
     *                 property="investment",
     *                 type="object",
     *                 @OA\Property(property="investment_cost", type="number", example=217900000),
     *                 @OA\Property(property="growth_in_market_value", type="number", example=0),
     *                 @OA\Property(
     *                     property="total_income",
     *                     type="object",
     *                     @OA\Property(property="mount_income", type="number", example=0),
     *                     @OA\Property(property="percent", type="number", example=0)
     *                 ),
     *                 @OA\Property(property="annual_expense", type="number", example=0),
     *                 @OA\Property(
     *                     property="annual_net_operating_margin",
     *                     type="object",
     *                     @OA\Property(property="mount_margin", type="number", example=0),
     *                     @OA\Property(property="percent_margin", type="number", example=0)
     *                 ),
     *                 @OA\Property(property="annual_investment_growth", type="number", example=0),
     *                 @OA\Property(property="return_on_investment_period", type="number", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function calculatePaymentPlan(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'purchase_duration' => 'required|integer|min:1',
            'standing' => 'required|in:high,medium,low',
        ]);

        $product = Product::with('productable.buildingFinances', 'productable.buildingInvestment')->findOrFail($validated['product_id']);

        if ($product->productable_type !== 'App\\Models\\Property') {
            return response()->json([
                'success' => false,
                'message' => 'Ce produit n\'est pas une propriété (Property)',
            ], 422);
        }

        $buildingFinance = collect($product->productable->buildingFinances ?? [])
            ->firstWhere('type_of_standing', $validated['standing']);

        if (!$buildingFinance) {
            return response()->json([
                'success' => false,
                'message' => "Aucun finance trouvé pour le standing '{$validated['standing']}'",
            ], 422);
        }

        $totalCost = $buildingFinance->total_building_finance;
        $durationInMonths = $validated['purchase_duration'];

        $monthlyPayment = $totalCost / $durationInMonths;
        
        $paymentPlan = [
            'total_cost' => (float) $totalCost,
            'duration_months' => $durationInMonths,
            'weekly' => round($monthlyPayment / 4, 2),
            'monthly' => round($monthlyPayment, 2),
            'quarterly' => round($monthlyPayment * 3, 2),
            'semester' => round($monthlyPayment * 6, 2),
            'annual' => round($monthlyPayment * 12, 2),
        ];

        $investmentCost = (float) $totalCost;
        $investment = $product->productable->buildingInvestment;
        
        $growthInMarketValue = $investment ? round((float) $investment->growth_in_market_value, 2) : 0;
        $annualExpense = $investment ? round((float) $investment->annual_expense, 2) : 0;
        
        $mountIncome = 0; 
        if ($investment) {
            $mountIncome = round((float) ($investment->mount_income ?? 0), 2);
        }
        
        $percentIncome = $investmentCost > 0 ? round(($mountIncome * 100) / $investmentCost, 2) : 0;
        
        $mountMargin = round($mountIncome - $annualExpense, 2);
        $percentMargin = $investmentCost > 0 ? round(($mountMargin * 100) / $investmentCost, 2) : 0;
        
        $annualInvestmentGrowth = round($percentMargin + $growthInMarketValue, 2);
        
        $returnOnInvestmentPeriod = ($percentMargin > 0) 
            ? round(100 / $percentMargin, 2) 
            : null;

        $investmentData = [
            'investment_cost' => $investmentCost,
            'growth_in_market_value' => $growthInMarketValue,
            'total_income' => [
                'mount_income' => $mountIncome,
                'percent' => $percentIncome,
            ],
            'annual_expense' => $annualExpense,
            'annual_net_operating_margin' => [
                'mount_margin' => $mountMargin,
                'percent_margin' => $percentMargin,
            ],
            'annual_investment_growth' => $annualInvestmentGrowth,
            'return_on_investment_period' => $returnOnInvestmentPeriod,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Plan de paiement calculé',
            'payment_plan' => $paymentPlan,
            'investment' => $investmentData,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/products/{productId}/land-investment-analysis",
     *     summary="Analyser l'investissement pour un terrain (Land)",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"area","growth_in_market_value","number_conservation_years"},
     *             @OA\Property(property="area", type="number", example=5000, description="Superficie en m²"),
     *             @OA\Property(property="growth_in_market_value", type="number", example=5.5, description="Croissance de la valeur marchande en %"),
     *             @OA\Property(property="number_conservation_years", type="integer", example=10, description="Nombre d'années de conservation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Analyse d'investissement calculée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Analyse d'investissement calculée"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="purchase_price", type="number", example=25000000, description="Prix d'achat total (unit_price * area)"),
     *                 @OA\Property(property="growth_in_market_value", type="number", example=5.5, description="Croissance de la valeur marchande en %"),
     *                 @OA\Property(property="number_conservation_years", type="integer", example=10),
     *                 @OA\Property(property="price_based_number_of_years", type="number", example=2750, description="Prix basé sur le nombre d'années (number_conservation_years * growth_in_market_value * unit_price)"),
     *                 @OA\Property(property="maturity_amount", type="number", example=13750000, description="Montant à maturité (price_based_number_of_years * area)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Le produit n'est pas un terrain (Land)"
     *     )
     * )
     */
    public function landInvestmentAnalysis(Request $request, $productId)
    {
        $validated = $request->validate([
            'area' => 'required|numeric|min:0',
            'growth_in_market_value' => 'required|numeric|min:0',
            'number_conservation_years' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($productId);

        if ($product->productable_type !== 'App\\Models\\Land') {
            return response()->json([
                'success' => false,
                'message' => 'Ce produit n\'est pas un terrain (Land)',
            ], 422);
        }

        $unitPrice = (float) $product->unit_price;
        $area = (float) $validated['area'];
        $growthInMarketValue = (float) $validated['growth_in_market_value'];
        $numberConservationYears = (int) $validated['number_conservation_years'];

        $purchasePrice = round($unitPrice * $area, 2);
        $priceBasedNumberOfYears = round($numberConservationYears * $growthInMarketValue * $unitPrice, 2);
        $maturityAmount = round($priceBasedNumberOfYears * $area, 2);

        return response()->json([
            'success' => true,
            'message' => 'Analyse d\'investissement calculée',
            'data' => [
                'purchase_price' => $purchasePrice,
                'growth_in_market_value' => $growthInMarketValue,
                'number_conservation_years' => $numberConservationYears,
                'price_based_number_of_years' => $priceBasedNumberOfYears,
                'maturity_amount' => $maturityAmount,
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/products/{productId}/propose-land",
     *     summary="Proposer un terrain (Land) comme produit associé",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID du produit principal"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"land_product_id"},
     *             @OA\Property(
     *                 property="land_product_id",
     *                 type="integer",
     *                 example=38,
     *                 description="ID du produit Land à proposer"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Land proposé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Land proposé avec succès")
     *         )
     *     )
     * )
     */
    public function proposeLand(Request $request, $productId)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin', 'api')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux administrateurs',
            ], 403);
        }

        $validated = $request->validate([
            'land_product_id' => 'required|integer|exists:products,id',
        ]);

        $mainProduct = Product::findOrFail($productId);
        $landProduct = Product::findOrFail($validated['land_product_id']);

        if ($landProduct->productable_type !== 'App\\Models\\Land') {
            return response()->json([
                'success' => false,
                'message' => 'Le produit sélectionné n\'est pas un terrain (Land)',
            ], 422);
        }

        $exists = \App\Models\ProposedProduct::where('product_id', $productId)
            ->where('proposed_product_id', $validated['land_product_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Ce terrain est déjà proposé pour ce produit',
            ], 422);
        }

        \App\Models\ProposedProduct::create([
            'product_id' => $productId,
            'proposed_product_id' => $validated['land_product_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Land proposé avec succès',
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/products/{productId}/propose-property",
     *     summary="Proposer une propriété (Property) comme produit associé",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID du produit principal"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"property_product_id"},
     *             @OA\Property(
     *                 property="property_product_id",
     *                 type="integer",
     *                 example=41,
     *                 description="ID du produit Property à proposer"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Property proposée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Property proposée avec succès")
     *         )
     *     )
     * )
     */
    public function proposeProperty(Request $request, $productId)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin', 'api')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux administrateurs',
            ], 403);
        }

        $validated = $request->validate([
            'property_product_id' => 'required|integer|exists:products,id',
        ]);

        $mainProduct = Product::findOrFail($productId);
        $propertyProduct = Product::findOrFail($validated['property_product_id']);

        if ($propertyProduct->productable_type !== 'App\\Models\\Property') {
            return response()->json([
                'success' => false,
                'message' => 'Le produit sélectionné n\'est pas une propriété (Property)',
            ], 422);
        }

        $exists = \App\Models\ProposedProduct::where('product_id', $productId)
            ->where('proposed_product_id', $validated['property_product_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Cette propriété est déjà proposée pour ce produit',
            ], 422);
        }

        \App\Models\ProposedProduct::create([
            'product_id' => $productId,
            'proposed_product_id' => $validated['property_product_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Property proposée avec succès',
        ], 201);
    }
}
