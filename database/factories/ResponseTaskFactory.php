<?php

namespace Database\Factories;

use App\Models\ResponseCase;
use App\Models\ResponseTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResponseTask>
 */
class ResponseTaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'response_case_id' => ResponseCase::factory(),
            'title' => fake()->randomElement([
                'Inspect the cooling unit',
                'Call the electricity provider',
                'Replace the pump pressure valve',
                'Check every floor with residents',
            ]),
            'description' => fake()->optional()->sentence(),
            'status' => ResponseTask::STATUS_TODO,
            'progress' => 0,
            'due_date' => fake()->optional()->dateTimeBetween('now', '+2 weeks'),
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => ['status' => ResponseTask::STATUS_DONE]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => ['due_date' => now()->subDays(3)]);
    }
}
