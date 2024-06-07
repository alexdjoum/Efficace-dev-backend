<?php

namespace Database\Factories;

use App\Models\Land;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class LandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            "area" => fake("en")->numberBetween(1, 100),
            "is_enagmentable" => fake("en")->boolean(),
            "relief" => fake("en")->word(),
            "description" => fake("en")->sentence(50),
            "land_title" => fake("en")->title(),
            "certificat_of_ownership" => fake("en")->boolean(),
            "technical_doc" => fake("en")->boolean(),
        ];
    }

    public function withLocation()
    {
        return $this->afterMaking(function (Land $land) {
            $land->location()->create();
        });
    }

    public function configure()
    {
        return $this->afterMaking(function (Land $land) {
            $location = LocationFactory::new()->create();
            $land->location()->associate($location);
        })->afterCreating(function (Land $land) {
            for ($i = 0; $i < 2; $i++) {
                $url = fake()->image(public_path('storage/tmp'));
                $land->addMedia($url)->toMediaCollection('land');
            }
            for ($i = 0; $i < 5; $i++) {
                $land->enagments()->create(['area' => fake("en")->numberBetween(10, 100)]);
            }
        });
    }
}