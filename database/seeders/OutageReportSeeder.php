<?php

namespace Database\Seeders;

use App\Models\OutageReport;
use App\Models\PowerOutage;
use Illuminate\Database\Seeder;

class OutageReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $powerOutages = PowerOutage::query()->get();

        foreach ($powerOutages as $powerOutage) {
            OutageReport::factory()->count(2)->create([
                'power_outage_id' => $powerOutage->id,
            ]);
        }
    }
}
