<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\TranslationService;

class PropertyResource extends JsonResource
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
            'title' => $shouldTranslate ? $this->translator->translate($this->title) : $this->title,
            'description' => $shouldTranslate ? $this->translator->translate($this->description) : $this->description,
            'type' => $this->type,
            'type_translated' => __('attributes.' . $this->type),
            'build_area' => $this->build_area,
            'field_area' => $this->field_area,
            'levels' => $this->levels,
            'parkings' => $this->parkings,
            'has_garden' => $this->has_garden,
            'has_pool' => $this->has_pool,
            'basement_area' => $this->basement_area,
            'productable' => $this->productable,
            'ground_floor_area' => $this->ground_floor_area,
            'estimated_payment' => $this->estimated_payment,
        ];

        if ($this->type === 'building') {
            $data['number_of_appartements'] = $this->number_of_appartements;
            $data['overall_program'] = $this->getOverallProgram($shouldTranslate);
            $data['investment'] = $this->getInvestment();
            $data['building_finances'] = $this->getBuildingFinances();
            $data['part_of_buildings'] = $this->getPartOfBuildings($shouldTranslate);
        } else {
            $data['bedrooms'] = $this->bedrooms;
            $data['bathrooms'] = $this->bathrooms;
            $data['number_of_salons'] = $this->number_of_salons;
        }

        if ($this->location) {
            $data['location'] = [
                'id' => $this->location->id,
                'coordinate_link' => $this->location->coordinate_link,
                'kml' => $this->location->kml,
                'address' => $this->location->address ? [
                    'id' => $this->location->address->id,
                    'street' => $this->location->address->street,
                    'city' => $this->location->address->city,
                    'country' => $this->location->address->country,
                ] : null,
            ];
        }

        $data['images'] = $this->getMedia('property')->map(fn($media) => $media->getUrl());

        return $data;
    }

    protected function getOverallProgram($shouldTranslate = false)
    {
        if ($this->partOfBuildings->isEmpty()) {
            return [];
        }

        return $this->partOfBuildings
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

    protected function getInvestment()
    {
        if (!($property instanceof \App\Models\Property) || $property->type !== 'building') {
            return null;
        }

        if (isset($property->investment)) {
            return $property->investment;
        }

        $mediumFinance = $this->buildingFinances->firstWhere('type_of_standing', 'medium');

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
        foreach ($this->partOfBuildings as $part) {
            if ($part->mount_of_part && $part->number_of_part) {
                $mountIncome += (float) $part->mount_of_part * (int) $part->number_of_part;
            }
        }
        $mountIncome = round($mountIncome, 2);
        $percentIncome = $investmentCost > 0 ? round(($mountIncome * 100) / $investmentCost, 2) : 0;

        $mountMargin = round($investmentCost - $annualExpense, 2);
        $percentMargin = $investmentCost > 0 ? round($mountMargin / $investmentCost, 2) : 0;

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

    protected function getBuildingFinances()
    {
        return $this->buildingFinances->map(function ($finance) {
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

    protected function getPartOfBuildings($shouldTranslate = false)
    {
        return $this->partOfBuildings->map(function ($part) use ($shouldTranslate) {
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
}