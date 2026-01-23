<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\TranslationService;

class ProductResource extends JsonResource
{
    protected $translator;

    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->translator = new TranslationService();
    }

    public function toArray($request)
    {
        $currentLocale = app()->getLocale();
        $shouldTranslate = $currentLocale !== 'fr';
        $data = [
            'id' => $this->id,
            'reference' => $this->reference,
            'for_rent' => $this->for_rent,
            'for_sale' => $this->for_sale,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'status' => __('attributes.' . $this->status),
            'description' => $shouldTranslate ? $this->translator->translate($this->description) : $this->description,
            'published_at' => $this->published_at,
            'productable_type' => $this->productable_type,
            'productable_id' => $this->productable_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->productable) {
            if ($this->productable_type === 'App\\Models\\Land') {
                $data['productable'] = $this->getLandData();
            } elseif ($this->productable_type === 'App\\Models\\Property') {
                $data['productable'] = $this->getPropertyData();
            }
        }

        $data['proposed_products'] = $this->getProposedProducts();

        return $data;
    }

    protected function getLandData()
    {
        $land = $this->productable;

        return [
            'id' => $land->id,
            'area' => $land->area,
            'is_fragmentable' => $land->is_fragmentable,
            'relief' => $land->relief,
            'description' => $land->description,
            'location_id' => $land->location_id,
            'certificat_of_ownership' => $land->certificat_of_ownership,
            'technical_doc' => $land->technical_doc,
            'land_title' => $land->land_title,
            'images' => $land->getMedia('land')->map(fn($media) => $media->getUrl()),
            'kml_file' => $this->getKmlFile($land),
            'location' => $this->getLocation($land->location),
            'fragments' => $land->fragments,
            'video_lands' => $land->videoLands,
            'proposed_sites' => [], 
        ];
    }

    protected function getPropertyData()
    {
        $property = $this->productable;
        $currentLocale = app()->getLocale();
        $shouldTranslate = $currentLocale !== 'fr';

        $data = [
            'id' => $property->id,
            'title' => $shouldTranslate ? $this->translator->translate($property->title) : $property->title,
            'build_area' => $property->build_area,
            'field_area' => $property->field_area,
            'levels' => $property->levels,
            'has_garden' => $property->has_garden,
            'parkings' => $property->parkings,
            'has_pool' => $property->has_pool,
            'basement_area' => $property->basement_area,
            'ground_floor_area' => $property->ground_floor_area,
            'type' => __('attributes.' . $property->type),
            //'type_translated' => __('attributes.' . $property->type),
            'description' => $shouldTranslate ? $this->translator->translate($property->description) : $property->description,
            'estimated_payment' => $property->estimated_payment,
            'images' => $property->getMedia('property')->map(fn($media) => $media->getUrl()),
            'location' => $this->getLocation($property->location),
        ];

        if ($property->type === 'building') {
            $data['number_of_appartements'] = $property->number_of_appartements;
            $data['overall_program'] = $this->getOverallProgram($property);
            $data['investment'] = $this->getInvestment($property);
            $data['building_finances'] = $this->getBuildingFinances($property);
            $data['part_of_buildings'] = $this->getPartOfBuildings($property, $shouldTranslate);
            $data['accommodations'] = $property->accommodations;
            $data['retail_spaces'] = $property->retail_spaces;
        } else {
            $data['bedrooms'] = $property->bedrooms;
            $data['bathrooms'] = $property->bathrooms;
            $data['number_of_salons'] = $property->number_of_salons;
        }

        $data['proposed_sites'] = []; 

        return $data;
    }

    protected function getProposedProducts()
    {
        if ($this->proposedProducts->isEmpty()) {
            return [];
        }

        $currentLocale = app()->getLocale();
        $shouldTranslate = $currentLocale !== 'fr';

        return $this->proposedProducts->map(function ($proposedProduct) use ($shouldTranslate) {
            $data = [
                'id' => $proposedProduct->id,
                'reference' => $proposedProduct->reference,
                'description' => $shouldTranslate 
                    ? $this->translator->translate($proposedProduct->description) 
                    : $proposedProduct->description,
                'for_sale' => $proposedProduct->for_sale,
                'for_rent' => $proposedProduct->for_rent,
                'unit_price' => $proposedProduct->unit_price,
                'total_price' => $proposedProduct->total_price,
                'status' => $proposedProduct->status,
                'status_translated' => $this->getTranslatedStatus($proposedProduct),
                'productable_type' => $proposedProduct->productable_type,
            ];

            if ($proposedProduct->productable_type === 'App\\Models\\Land') {
                $land = $proposedProduct->productable;
                $data['productable'] = [
                    'id' => $land->id,
                    'area' => $land->area,
                    'land_title' => $shouldTranslate 
                        ? $this->translator->translate($land->land_title) 
                        : $land->land_title,
                    'images' => $land->getMedia('land')->map(fn($media) => $media->getUrl()),
                    'kml_file' => $this->getKmlFile($land),
                    'location' => $this->getLocation($land->location),
                    'fragments' => $land->fragments,
                    'video_lands' => $land->videoLands,
                ];
            } elseif ($proposedProduct->productable_type === 'App\\Models\\Property') {
                $property = $proposedProduct->productable;
                $data['productable'] = [
                    'id' => $property->id,
                    'title' => $shouldTranslate 
                        ? $this->translator->translate($property->title) 
                        : $property->title,
                    'type' => $property->type,
                    'type_translated' => __('attributes.' . $property->type),
                    'building_finances' => $this->getBuildingFinances($property),
                    'part_of_buildings' => $this->getPartOfBuildingsForProperty($property, $shouldTranslate),
                    'location' => $this->getLocation($property->location),
                ];
            }

            return $data;
        });
    }

    protected function getKmlFile($land)
    {
        $kmlMedia = $land->getFirstMedia('kml');
        
        if (!$kmlMedia && $land->location) {
            $kmlMedia = $land->location->getFirstMedia('kml');
        }
        
        return $kmlMedia ? [
            'url' => $kmlMedia->getUrl(),
            'name' => $kmlMedia->file_name,
            'size' => $kmlMedia->size,
            'mime_type' => $kmlMedia->mime_type,
        ] : null;
    }

    protected function getLocation($location)
    {
        if (!$location) {
            return null;
        }

        return [
            'id' => $location->id,
            'coordinate_link' => $location->coordinate_link,
            'kml' => $location->kml,
            'address' => $location->address ? [
                'id' => $location->address->id,
                'street' => $location->address->street,
                'city' => $location->address->city,
                'country' => $location->address->country,
            ] : null,
        ];
    }

    protected function getOverallProgram($property, $shouldTranslate = false)
    {
        if ($property->partOfBuildings->isEmpty()) {
            return [];
        }

        return $property->partOfBuildings
            ->filter(fn($part) => $part->typeOfPartOfTheBuilding !== null)
            ->groupBy('typeOfPartOfTheBuilding.id')
            ->map(function ($parts) use ($shouldTranslate) {
                $firstPart = $parts->first();
                $typeName = $firstPart->typeOfPartOfTheBuilding->name;
                
                return [
                    'type_id' => $firstPart->typeOfPartOfTheBuilding->id,
                    'type_name' => $shouldTranslate ? $this->translator->translate($typeName) : $typeName,
                    'count' => $parts->count()
                ];
            })
            ->values();
    }

    protected function getInvestment($property)
    {
        $mediumFinance = $property->buildingFinances->firstWhere('type_of_standing', 'medium');

        if (!$mediumFinance) {
            return null;
        }

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
        foreach ($property->partOfBuildings as $part) {
            if ($part->mount_of_part && $part->number_of_part) {
                $mountIncome += (float) $part->mount_of_part * (int) $part->number_of_part;
            }
        }
        $mountIncome = round($mountIncome, 2);
        $percentIncome = $investmentCost > 0 ? round(($mountIncome * 100) / $investmentCost, 2) : 0;

        $mountMargin = round($mountIncome - $annualExpense, 2);
        $percentMargin = $investmentCost > 0 ? round(($mountMargin * 100) / $investmentCost, 2) : 0;

        $annualInvestmentGrowth = round($percentMargin + $growthInMarketValue, 2);

        $returnOnInvestmentPeriod = ($percentMargin > 0) 
            ? round(100 / $percentMargin, 2) 
            : null;

        return [
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

    protected function getBuildingFinances($property)
    {
        return $property->buildingFinances->map(function ($finance) {
            return [
                'id' => $finance->id,
                'property_id' => $finance->property_id,
                'type_of_standing' => $finance->type_of_standing,
                'standing_translated' => __('attributes.' . $finance->type_of_standing),
                'project_study' => $finance->project_study,
                'building_permit' => $finance->building_permit,
                'structural_work' => $finance->structural_work,
                'finishing' => $finance->finishing,
                'equipments' => $finance->equipments,
                'cost_of_land' => $finance->cost_of_land,
                'total_excluding_field' => $finance->total_excluding_field,
                'total_building_finance' => $finance->total_building_finance,
            ];
        });
    }

    protected function getPartOfBuildings($property, $shouldTranslate = false)
    {
        return $property->partOfBuildings->map(function ($part) use ($shouldTranslate) {
            return [
                'id' => $part->id,
                'title' => $shouldTranslate ? $this->translator->translate($part->title) : $part->title,
                'description' => $shouldTranslate ? $this->translator->translate($part->description) : $part->description,
                'property_id' => $part->property_id,
                'type_of_part_of_the_building_id' => $part->type_of_part_of_the_building_id,
                'mount_of_part' => $part->mount_of_part,
                'number_of_part' => $part->number_of_part,
                'photos' => $part->getMedia('part_photos')->map(fn($media) => $media->getUrl()),
                'type_of_part_of_the_building' => $part->typeOfPartOfTheBuilding ? [
                    'id' => $part->typeOfPartOfTheBuilding->id,
                    'name' => $shouldTranslate 
                        ? $this->translator->translate($part->typeOfPartOfTheBuilding->name) 
                        : $part->typeOfPartOfTheBuilding->name,
                ] : null,
            ];
        });
    }

    protected function getTranslatedStatus()
    {
        if (in_array($this->status, ['Publié', 'Disponible', 'Vendu', 'Réservé'])) {
            $statusMap = [
                'Publié' => __('attributes.published'),
                'Disponible' => __('attributes.available'),
                'Vendu' => __('attributes.sold'),
                'Réservé' => __('attributes.reserved'),
            ];
            return $statusMap[$this->status] ?? $this->status;
        }
        
        return __('attributes.' . strtolower($this->status));
    }
}