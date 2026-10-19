<?php

namespace Database\Seeders;

use App\Models\Neighborhood;
use App\Models\PowerOutage;
use Illuminate\Database\Seeder;

class PowerOutageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $outages = [
            [
                'title' => 'Main boulevard outage',
                'neighborhood' => 'Centre Ville',
                'status' => 'in_progress',
                'severity' => 'high',
                'started_at' => now()->subMinutes(45),
                'estimated_end_at' => now()->addHours(2),
                'description' => 'A transformer issue is causing a service interruption on the main boulevard.',
            ],
            [
                'title' => 'South building maintenance',
                'neighborhood' => 'Ben Arous',
                'status' => 'planned',
                'severity' => 'moderate',
                'started_at' => now()->addHours(3),
                'estimated_end_at' => now()->addHours(8),
                'description' => 'Scheduled maintenance work is expected to affect several residential blocks.',
            ],
            [
                'title' => 'Network restoration',
                'neighborhood' => 'Ariana',
                'status' => 'resolved',
                'severity' => 'low',
                'started_at' => now()->subDays(2),
                'estimated_end_at' => now()->subDay(),
                'description' => 'The local network has been restored after an earlier interruption.',
            ],
        ];

        foreach ($outages as $data) {
            $neighborhood = Neighborhood::query()->where('name', $data['neighborhood'])->firstOrFail();
            unset($data['neighborhood']);

            $powerOutage = PowerOutage::firstOrCreate(
                ['title' => $data['title']],
                $data,
            );

            if ($powerOutage->neighborhoods()->doesntExist()) {
                $powerOutage->neighborhoods()->attach($neighborhood);
            }
        }
    }
}
