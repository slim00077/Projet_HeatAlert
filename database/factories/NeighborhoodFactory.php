<?php

namespace Database\Factories;

use App\Models\Neighborhood;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Neighborhood>
 */
class NeighborhoodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $population = fake()->numberBetween(5000, 120000);

        return [
            'name' => fake()->unique()->city(),
            'description' => fake()->optional()->sentence(),
            'latitude' => fake()->latitude(36.7, 36.9),
            'longitude' => fake()->longitude(10.1, 10.35),
            'population' => $population,
            'vulnerable_population' => (int) ($population * fake()->randomFloat(2, 0.05, 0.25)),
        ];
    }
}
