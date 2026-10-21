<?php

namespace Database\Factories;

use App\Enums\ResponseStage;
use App\Models\Residence;
use App\Models\ResponseCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResponseCase>
 */
class ResponseCaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'residence_id' => Residence::factory(),
            'title' => fake()->randomElement([
                'Cooling failure in block B',
                'Repeated power cuts on upper floors',
                'Rooftop water tank pressure loss',
                'Elevator breakdowns during heat waves',
            ]),
            'summary' => fake()->optional()->sentence(),
            'priority' => fake()->randomElement(array_keys(ResponseCase::PRIORITIES)),
            'target_date' => fake()->optional()->dateTimeBetween('now', '+3 weeks'),
        ];
    }

    public function stage(ResponseStage $stage): static
    {
        return $this->state(fn () => ['stage' => $stage]);
    }
}
