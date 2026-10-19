<?php

namespace Database\Factories;

use App\Models\PowerOutage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PowerOutage>
 */
class PowerOutageFactory extends Factory
{
    protected $model = PowerOutage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'District outage',
            'status' => 'in_progress',
            'severity' => 'high',
            'started_at' => now()->subHours(2),
            'estimated_end_at' => now()->addHours(3),
            'description' => 'A planned interruption is affecting part of the neighborhood.',
        ];
    }
}
