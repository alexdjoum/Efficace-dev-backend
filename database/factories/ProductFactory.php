<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $state = fake('fr')->boolean();
        return [
            "for_rent" => $state,
            "for_sale" => !$state,
            "unit_price" => fake('en')->numberBetween(10000, 100000),
            "total_price" => fake('en')->numberBetween(1000000, 10000000),
            "description" => fake('en')->sentence(50),
            "status" => fake('en')->randomElement(['En attente', 'Validé', 'Terminé']),
        ];
    }

    public function configure()
    {
        return $this->afterMaking(function (Product $product) {
            $land = LandFactory::new()->create();
            $product->productable()->associate($land);
        });
    }
}
