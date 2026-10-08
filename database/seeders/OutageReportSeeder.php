<?php

namespace Database\Seeders;

use App\Models\OutageReport;
use App\Models\PowerOutage;
use App\Models\User;
use Illuminate\Database\Seeder;

class OutageReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Reports are attached to existing residents instead of creating new users.
     */
    public function run(): void
    {
        $residents = User::where('role', User::ROLE_RESIDENT)->get();

        foreach (PowerOutage::query()->get() as $powerOutage) {
            for ($i = 0; $i < 2; $i++) {
                $reporter = $residents->isNotEmpty() ? $residents->random() : User::factory()->create();

                OutageReport::factory()->create([
                    'power_outage_id' => $powerOutage->id,
                    'user_id' => $reporter->id,
                    'reporter_name' => $reporter->name,
                    'email' => $reporter->email,
                ]);
            }
        }
    }
}
