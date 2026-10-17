<?php

namespace Database\Factories;

use App\Models\Residence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Residence>
 */
class ResidenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Residence Les Palmiers', 'Residence El Yasmine', 'Residence Les Oliviers', 'Residence Ennasr']),
            'address' => fake()->streetAddress(),
            'neighborhood' => fake()->randomElement(['Centre Ville', 'La Marsa', 'Ariana', 'Ben Arous']),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => Residence::STATUS_PENDING]);
    }

    public function rejected(string $reason = 'Address could not be verified.'): static
    {
        return $this->state(fn () => ['status' => Residence::STATUS_REJECTED, 'rejection_reason' => $reason]);
    }
}
