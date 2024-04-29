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
                $product->productable()->associate($land);
                $product->save();
                break;
            case 'property':
                $property = Property::query()->findOrFail($data['productable_id']);
                $product->productable()->associate($property);
                $product->save();
                break;
            case 'accommodation':
                $accommodation = Accommodation::query()->findOrFail($data['productable_id']);
                $product->productable()->associate($accommodation);
                $product->save();
                break;
            case 'virtual':
                $virtual = Virtual::query()->findOrFail($data['productable_id']);
                $product->productable()->associate($virtual);
                $product->save();
                break;
            case 'retail_space':
                $retail_space = RetailSpace::query()->findOrFail($data['productable_id']);
                $product->productable()->associate($retail_space);
                $product->save();
                break;
        }


        return $product->fresh();
    }

    public function update(Product $product, array $data)
    {
        $product->update($data);

        return $product->fresh();
    }
}
