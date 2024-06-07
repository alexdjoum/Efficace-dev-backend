<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            "coordinate_link" => fake("en")->url(),
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Location $location) {
            $address = AddressFactory::new()->make();
            $location->address()->save($address);
        });
    }
}
