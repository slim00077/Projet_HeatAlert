<?php

namespace Database\Seeders;

use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Safe to run several times: existing rows are reused.
     * All demo accounts use the development-only password "password".
     */
    public function run(): void
    {
        User::where('email', 'admin@heatalert.test')->first()
            ?? User::factory()->admin()->create([
                'name' => 'HeatAlert Admin',
                'email' => 'admin@heatalert.test',
            ]);

        $residents = collect([
            ['Amine Ben Salah', 'amine@heatalert.test'],
            ['Sarra Trabelsi', 'sarra@heatalert.test'],
            ['Karim Mansouri', 'karim@heatalert.test'],
        ])->map(fn (array $data) => User::where('email', $data[1])->first()
            ?? User::factory()->create(['name' => $data[0], 'email' => $data[1]]));

        $residences = [
            ['Residence Les Palmiers', '12 Avenue Habib Bourguiba', 'Centre Ville', 'Six-floor building with a shared rooftop terrace.'],
            ['Residence El Yasmine', '45 Rue de la Marsa', 'La Marsa', 'Seaside residence with many elderly residents.'],
            ['Residence Les Oliviers', '8 Rue des Oliviers', 'Ariana', null],
            ['Residence Ennasr', '230 Avenue Ennasr', 'Ben Arous', 'Large complex without air-conditioned common areas.'],
            ['Residence Les Jasmins', '17 Rue des Jasmins', 'La Marsa', null],
        ];

        $reports = [
            ['Air conditioning broken in lobby', 'The lobby cooling system has been out for three days during the heat wave.', 'Pending'],
            ['Power outage on 3rd floor', 'Repeated power cuts every afternoon affecting several apartments.', 'In Progress'],
            ['Elevator stopped in the heat', 'Elderly residents cannot reach their apartments safely.', 'Resolved'],
        ];

        foreach ($residences as $index => [$name, $address, $neighborhood, $description]) {
            $owner = $residents[$index % $residents->count()];

            $residence = Residence::firstOrCreate(
                ['user_id' => $owner->id, 'name' => $name],
                ['address' => $address, 'neighborhood' => $neighborhood, 'description' => $description],
            );

            foreach (array_slice($reports, 0, 2 + ($index % 2)) as $reportIndex => [$title, $text, $status]) {
                ResidentReport::firstOrCreate(
                    ['residence_id' => $residence->id, 'title' => $title],
                    [
                        'user_id' => $owner->id,
                        'description' => $text,
                        'status' => $status,
                        'reported_at' => Carbon::create(2026, 9, 15)->addDays($index * 3 + $reportIndex),
                    ],
                );
            }
        }

        $this->call([
            HeatAlertSeeder::class,
            CoolSpotSeeder::class,
            AdviceSeeder::class,
            PowerOutageSeeder::class,
            OutageReportSeeder::class,
        ]);
    }
}
