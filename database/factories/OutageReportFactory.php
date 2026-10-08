<?php

namespace Database\Factories;

use App\Models\OutageReport;
use App\Models\PowerOutage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutageReport>
 */
class OutageReportFactory extends Factory
{
    protected $model = OutageReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'power_outage_id' => PowerOutage::factory(),
            'user_id' => User::factory(),
            'reporter_name' => fake()->name(),
            'email' => fake()->email(),
            'description' => fake()->paragraph(),
            'status' => 'pending',
        ];
    }
}
