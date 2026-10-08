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
            'reporter_name' => fn (array $attributes) => User::find($attributes['user_id'])->name,
            'email' => fn (array $attributes) => User::find($attributes['user_id'])->email,
            'description' => fake()->paragraph(),
            'status' => 'pending',
        ];
    }
}
