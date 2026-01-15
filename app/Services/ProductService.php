<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Land;
use App\Models\Property;
use App\Models\Accommodation;
use App\Models\Virtual;
use App\Models\RetailSpace;

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
        
        if (isset($data['proposed_product_ids']) && is_array($data['proposed_product_ids'])) {
            \App\Models\ProposedProduct::where('product_id', $product->id)->delete();
            
            foreach ($data['proposed_product_ids'] as $proposedProductId) {
                \App\Models\ProposedProduct::create([
                    'product_id' => $product->id,
                    'proposed_product_id' => $proposedProductId,
                ]);
            }
        }
    
        return $product->fresh(['productable', 'proposedProducts']);
    }

    public function update(Product $product, array $data)
    {
        $product->update($data);
        
        return $product->fresh(['productable']);
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