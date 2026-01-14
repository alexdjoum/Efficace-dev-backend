<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Models\Property;
use App\Services\PropertyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;


class PropertyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Property::with([
            'partOfBuildings.typeOfPartOfTheBuilding',
            'buildingFinance.buildingInvestments',
            'operatingRatios',
        ]);

        if ($request->has('type') && in_array($request->type, ['villa', 'building'])) {
            $query->where('type', $request->type);
        }

        $properties = $query->get();

        $properties->each(function ($property) {
            if ($property->buildingFinance) {
                $property->total_building_finance = round(
                    (float) $property->buildingFinance->project_study 
                    + (float) $property->buildingFinance->building_permit 
                    + (float) $property->buildingFinance->structural_work 
                    + (float) $property->buildingFinance->finishing 
                    + (float) $property->buildingFinance->equipments 
                    + (float) $property->buildingFinance->cost_of_land,
                    2
                );
                
                $property->buildingFinance->makeHidden(['created_at', 'updated_at']);
            }
            
            if ($property->type === 'building' && $property->partOfBuildings->isNotEmpty()) {
                
                $typeCounts = $property->partOfBuildings
                    ->filter(function ($part) {
                        return $part->typeOfPartOfTheBuilding !== null;
                    })
                    ->groupBy('typeOfPartOfTheBuilding.id')
                    ->map(function ($parts, $typeId) {
                        $firstPart = $parts->first();
                        return [
                            'type_id' => $firstPart->typeOfPartOfTheBuilding->id,
                            'type_name' => $firstPart->typeOfPartOfTheBuilding->name,
                            'count' => $parts->count()
                        ];
                    })
                    ->values();
                
                $property->overall_program = $typeCounts;
                
                if ($property->buildingFinance) {
                    
                    // Investment Cost
                    $investmentCost = round(
                        (float) $property->buildingFinance->project_study 
                        + (float) $property->buildingFinance->building_permit 
                        + (float) $property->buildingFinance->structural_work 
                        + (float) $property->buildingFinance->finishing 
                        + (float) $property->buildingFinance->equipments 
                        + (float) $property->buildingFinance->cost_of_land,
                        2
                    );
                    
                    $growthInMarketValue = 0;
                    $annualExpense = 0;
                    
                    if ($property->buildingFinance->buildingInvestments->isNotEmpty()) {
                        $investment = $property->buildingFinance->buildingInvestments->first();
                        $growthInMarketValue = round((float) $investment->growth_in_market_value * 100, 2);
                        $annualExpense = round((float) $investment->annual_expense, 2);
                    }
                    
                    $mountIncome = 0;
                    
                    foreach ($property->operatingRatios as $ratio) {
                        $typeCount = $typeCounts->firstWhere('type_name', $ratio->type);
                        $count = $typeCount ? $typeCount['count'] : 0;
                        
                        $mountIncome += (float) $ratio->montant * $count;
                    }
                    
                    $mountIncome = round($mountIncome, 2);
                    $percentIncome = $investmentCost > 0 ? round(($mountIncome * 100) / $investmentCost, 2) : 0;
                    
                    // Annual Net Operating Margin
                    $mountMargin = round($mountIncome - $annualExpense, 2);
                    $percentMargin = $investmentCost > 0 ? round(($mountMargin * 100) / $investmentCost, 2) : 0;
                    
                    // Annual Investment Growth
                    $annualInvestmentGrowth = round($percentMargin + $growthInMarketValue, 2);
                    
                    // Return on Investment Period
                    $returnOnInvestmentPeriod = ($percentMargin > 0) 
                        ? round(100 / $percentMargin, 2) 
                        : null; 
                    
                    // object investment
                    $property->investment = [
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
                }
                
                $property->partOfBuildings->each(function ($part) {
                    $part->makeHidden(['media', 'created_at', 'updated_at']);
                    
                    if ($part->typeOfPartOfTheBuilding) {
                        $part->typeOfPartOfTheBuilding->makeHidden(['created_at', 'updated_at']);
                    }
                });
            } else {
                $property->overall_program = [];
            }
            
            if ($property->type === 'building') {
                $property->makeHidden(['bedrooms', 'bathrooms', 'number_of_salons']);
            } else {
                $property->makeHidden(['number_of_appartements', 'part_of_buildings', 'building_finance']);
            }

            $property->makeHidden([
                'proposed_sites',
                'accommodations',
                'retail_spaces',
                'created_at',
                'updated_at',
                'operating_ratios'
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Liste des propriétés',
            'data' => $properties
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePropertyRequest $request, PropertyService $propertyService)
    {
        try {
            $data = $request->except(['images']);
            
            if ($request->hasFile('images')) {
                $data['images'] = $request->file('images');
            }

            $property = $propertyService->create($data);

            return response()->json([
                'success' => true,
                'message' => 'Propriété créée avec succès.',
                'data' => $property
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Erreur création property', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la propriété',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Property $property)
    {
        return response()->json([
            'success' => true,
            'message' => 'Details de la propriété',
            'data' => $property
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePropertyRequest $request, Property $property, PropertyService $propertyService)
    {

        $property = DB::transaction(function () use ($request, $property, $propertyService) {
            return $propertyService->update($property, $request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Propriété modifié avec succès.',
            'data' => $property
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Property $property)
    {
        $property->location->address()->delete();
        $property->clearMediaCollection('property');
        $property->delete();

        return response()->json([
            'success' => true,
            'message' => 'Propriété supprimée avec succès.',
            'data' => null
        ]);
    }
}
