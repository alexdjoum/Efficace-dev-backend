<?php

namespace App\Services;

use App\Models\Land;
use App\Models\Product;
use App\Models\Virtual;
use App\Models\Property;
use App\Models\RetailSpace;
use App\Models\Accommodation;

class ProductService
{
    public function create(array $data)
    {
        $product = Product::query()->make($data);

        switch ($data['type']) {
            case 'land':
                $land = Land::query()->findOrFail($data['productable_id']);
                $this->validateProposedItemsAreProducts($land, Property::class, 'properties');
                $product->productable()->associate($land);
                break;
                
            case 'property':
                $property = Property::query()->findOrFail($data['productable_id']);
                $this->validateProposedItemsAreProducts($property, Land::class, 'lands');
                $product->productable()->associate($property);
                break;
                
            case 'accommodation':
                $accommodation = Accommodation::query()->findOrFail($data['productable_id']);
                $product->productable()->associate($accommodation);
                break;
                
            case 'virtual':
                $virtual = Virtual::query()->findOrFail($data['productable_id']);
                $product->productable()->associate($virtual);
                break;
                
            case 'retail_space':
                $retail_space = RetailSpace::query()->findOrFail($data['productable_id']);
                $product->productable()->associate($retail_space);
                break;
        }

        $product->save();
        return $product->fresh();
    }

    public function update(Product $product, array $data)
    {
        $product->update($data);

        return $product->fresh();
    }

    private function validateProposedItemsAreProducts($model, $proposableType, $itemName)
    {
        if (!$model->proposedSites()->exists()) {
            return; 
        }
        
        $proposedIds = $model->proposedSites()
            ->where('proposable_type', $proposableType)
            ->pluck('proposable_id');
        
        foreach ($proposedIds as $id) {
            $isProduct = Product::where('productable_type', $proposableType)
                ->where('productable_id', $id)
                ->exists();
            
            if (!$isProduct) {
                $itemNameSingular = rtrim($itemName, 's');
                throw new \Exception("Le {$itemNameSingular} #{$id} proposé doit d'abord être un product avant de pouvoir créer ce product.");
            }
        }
    }
}
