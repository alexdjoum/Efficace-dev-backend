<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;
use App\Models\PartOfBuilding;
use App\Services\PropertyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Models\TypeOfPartOfTheBuilding;
use App\Http\Resources\PropertyResource;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;


class PropertyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        $type = $request->input('type');
        $cacheKey = "properties:list:{$locale}:" . ($type ?? 'all');

        try {
            $cachedProperties = Redis::get($cacheKey);

            if ($cachedProperties) {
                return response()->json([
                    'success' => true,
                    'message' => __('messages.property_list'),
                    'data' => json_decode($cachedProperties, true),
                    'from_cache' => true,
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Redis cache read failed: ' . $e->getMessage());
        }

        $query = Property::with([
            'partOfBuildings.typeOfPartOfTheBuilding',
            'buildingFinances.buildingInvestment',
            'location.address',
        ]);

        if ($request->has('type') && in_array($request->type, ['villa', 'building'])) {
            $query->where('type', $request->type);
        }

        $properties = $query->orderBy('created_at', 'desc')->get();

        $propertiesResource = PropertyResource::collection($properties);

        try {
            Redis::setex($cacheKey, 864008, json_encode($propertiesResource));
        } catch (\Exception $e) {
            \Log::warning('Redis cache write failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.property_list'),
            'data' => $propertiesResource,
            'from_cache' => false, 
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
    public function show($id)
    {
        $property = Property::with([
            'partOfBuildings.typeOfPartOfTheBuilding',
            'buildingFinances.buildingInvestment',
            'operatingRatios',
            'location.address',
            'location.media',
            'proposedSites.proposable' => function ($query) {
                $query->with([
                    'location.address',
                    'location.media',
                    'fragments',
                    'videoLands'
                ]);
            }
        ])->findOrFail($id);

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
                ->map(function ($parts) {
                    $firstPart = $parts->first();
                    return [
                        'type_id' => $firstPart->typeOfPartOfTheBuilding->id,
                        'type_name' => $firstPart->typeOfPartOfTheBuilding->name,
                        'count' => $parts->count()
                    ];
                })
                ->values();
            
            $property->overall_program = $typeCounts;
            $mediumFinance = $property->buildingFinances->firstWhere('type_of_standing', 'medium');
            
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
                
                $growthInMarketValue = 0;
                $annualExpense = 0;
                
                if ($mediumFinance->buildingInvestment) {
                    $investment = $mediumFinance->buildingInvestment;
                    $growthInMarketValue = round((float) $investment->growth_in_market_value, 2);
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
                
                $mountMargin = round($mountIncome - $annualExpense, 2);
                $percentMargin = $investmentCost > 0 ? round(($mountMargin * 100) / $investmentCost, 2) : 0;
                
                $annualInvestmentGrowth = round($percentMargin + $growthInMarketValue, 2);
                
                $returnOnInvestmentPeriod = ($percentMargin > 0) 
                    ? round(100 / $percentMargin, 2) 
                    : null;
                
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
                
                $property->buildingFinance->makeHidden(['created_at', 'updated_at']);
            }

            $property->buildingFinances->each(function ($finance) {
                $finance->makeHidden(['created_at', 'updated_at']);
            });
            
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
            'operating_ratios',
            'created_at',
            'updated_at'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Détails de la propriété',
            'data' => $property
        ]);
        // return response()->json($property);
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

    public function add_parts(Request $request, $propertyId)
    {
        $property = Property::findOrFail($propertyId);

        if ($property->type !== 'building') {
            return response()->json([
                'success' => false,
                'message' => 'Parts can only be added to buildings' 
            ], 400);
        }

        $validated = $request->validate([
            'parts' => 'required|array',
            'parts.*.title' => 'required|string',
            'parts.*.description' => 'nullable|string',
            'parts.*.type_of_part_of_the_building_id' => 'nullable|exists:type_of_part_of_the_buildings,id',
            'parts.*.type_name' => 'nullable|string',
            'parts.*.mount_of_part' => 'nullable|numeric|min:0', 
            'parts.*.number_of_part' => 'nullable|integer|min:1'
        ]);

        $createdParts = [];

        foreach ($validated['parts'] as $index => $partData) {
            $typeId = null;
            if (isset($partData['type_of_part_of_the_building_id'])) {
                $type = TypeOfPartOfTheBuilding::findOrFail($partData['type_of_part_of_the_building_id']);
                $typeId = $type->id;
            } elseif (isset($partData['type_name'])) {
                $type = TypeOfPartOfTheBuilding::firstOrCreate(['name' => $partData['type_name']]);
                $typeId = $type->id;
            }

            $part = PartOfBuilding::create([
                'property_id' => $property->id,
                'title' => $partData['title'],
                'description' => $partData['description'] ?? null,
                'type_of_part_of_the_building_id' => $typeId,
                'mount_of_part' => $partData['mount_of_part'] ?? null, 
                'number_of_part' => $partData['number_of_part'] ?? 1,
            ]);

            $photoKey = "part_photos_{$index}";
            if ($request->hasFile($photoKey)) {
                foreach ($request->file($photoKey) as $photo) {
                    $part->addMedia($photo)->toMediaCollection('part_photos');
                }
            }

            $part->load('typeOfPartOfTheBuilding');
            $part->makeHidden(['media', 'created_at', 'updated_at']);
            
            $createdParts[] = $part;
        }

        return response()->json([
            'success' => true,
            'message' => count($createdParts) . ' part(s) added successfully',
            'data' => $createdParts
        ], 201);
    }

    public function update_one_part(Request $request, $propertyId, $partId)
    {
        \Log::info('All Request Data:', $request->all());
        \Log::info('Request Method: ' . $request->method());
        $property = Property::findOrFail($propertyId);
        $part = PartOfBuilding::where('property_id', $property->id)
            ->where('id', $partId)
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|string',
            'description' => 'sometimes|string',
            'type_of_part_of_the_building_id' => 'nullable|exists:type_of_part_of_the_buildings,id',
            'type_name' => 'nullable|string',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'replace_photos' => 'nullable|boolean', 
            'mount_of_part' => 'nullable|numeric|min:0', 
            'number_of_part' => 'nullable|integer|min:1', 
        ]);

        if (isset($validated['title'])) {
            $part->title = $validated['title'];
        }
        if (isset($validated['description'])) {
            $part->description = $validated['description'];
        }
        if (isset($validated['mount_of_part'])) { 
        $part->mount_of_part = $validated['mount_of_part'];
        }
        if (isset($validated['number_of_part'])) { 
            $part->number_of_part = $validated['number_of_part'];
        }

        if (isset($validated['type_of_part_of_the_building_id'])) {
            $type = TypeOfPartOfTheBuilding::findOrFail($validated['type_of_part_of_the_building_id']);
            $part->type_of_part_of_the_building_id = $type->id;
        } elseif (isset($validated['type_name'])) {
            $type = TypeOfPartOfTheBuilding::firstOrCreate(['name' => $validated['type_name']]);
            $part->type_of_part_of_the_building_id = $type->id;
        }

        $part->save();

        if ($request->hasFile('photos')) {
            $replacePhotos = $request->input('replace_photos', true);
            
            if ($replacePhotos) {
                $part->clearMediaCollection('part_photos');
            }
            
            foreach ($request->file('photos') as $photo) {
                $part->addMedia($photo)->toMediaCollection('part_photos');
            }
        }

        $part->load('typeOfPartOfTheBuilding');
        $part->makeHidden(['media', 'created_at', 'updated_at']);

        return response()->json([
            'success' => true,
            'message' => 'Partie mise à jour avec succès',
            'data' => $part
        ]);
    }

    public function delete_part($propertyId, $partId)
    {
        $property = Property::findOrFail($propertyId);
        
        $part = PartOfBuilding::where('property_id', $property->id)
            ->where('id', $partId)
            ->firstOrFail();

        $part->delete();

        return response()->json([
            'success' => true,
            'message' => 'Part deleted successfully'
        ]);
    }
}
