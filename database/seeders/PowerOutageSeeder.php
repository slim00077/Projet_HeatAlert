<?php

namespace Database\Seeders;

use App\Models\PowerOutage;
use Illuminate\Database\Seeder;

class PowerOutageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PowerOutage::factory()
            ->count(3)
            ->sequence(
                [
                    'title' => 'Main boulevard outage',
                    'area' => 'Downtown',
                    'status' => 'in_progress',
                    'severity' => 'high',
                    'started_at' => now()->subMinutes(45),
                    'estimated_end_at' => now()->addHours(2),
                    'description' => 'A transformer issue is causing a service interruption on the main boulevard.',
                ],
                [
                    'title' => 'South building maintenance',
                    'area' => 'South district',
                    'status' => 'planned',
                    'severity' => 'moderate',
                    'started_at' => now()->addHours(3),
                    'estimated_end_at' => now()->addHours(8),
                    'description' => 'Scheduled maintenance work is expected to affect several residential blocks.',
                ],
                [
                    'title' => 'Network restoration',
                    'area' => 'East community',
                    'status' => 'resolved',
                    'severity' => 'low',
                    'started_at' => now()->subDays(2),
                    'estimated_end_at' => now()->subDay(),
                    'description' => 'The local network has been restored after an earlier interruption.',
                ],
            )
            ->create();
    }
}
