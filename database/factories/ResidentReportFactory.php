<?php

namespace Database\Factories;

use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResidentReport>
 */
class ResidentReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'residence_id' => fn (array $attributes) => Residence::factory()->create(['user_id' => $attributes['user_id']])->id,
            'title' => fake()->randomElement([
                'Air conditioning broken in common area',
                'Power outage on 3rd floor',
                'Elevator stopped during heat wave',
                'No shade or water point in courtyard',
            ]),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(ResidentReport::STATUSES),
            'reported_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
