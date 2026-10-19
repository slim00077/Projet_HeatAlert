<?php

namespace Database\Factories;

use App\Enums\Severity;
use App\Models\HeatAlert;
use App\Models\Neighborhood;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeatAlert>
 */
class HeatAlertFactory extends Factory
{
    /**
     * Active by default: started yesterday, ends in three days.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'neighborhood_id' => Neighborhood::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->sentence(),
            'severity_level' => fake()->randomElement(Severity::cases()),
            'temperature' => fake()->randomFloat(1, 30, 46),
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(3),
        ];
    }

    public function severity(Severity $severity): static
    {
        return $this->state(fn () => ['severity_level' => $severity]);
    }

    public function upcoming(): static
    {
        return $this->state(fn () => ['start_date' => now()->addDays(2), 'end_date' => now()->addDays(5)]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['start_date' => now()->subDays(10), 'end_date' => now()->subDays(7)]);
    }

    public function openEnded(): static
    {
        return $this->state(fn () => ['end_date' => null]);
    }
}
