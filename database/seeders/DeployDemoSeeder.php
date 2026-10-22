<?php

namespace Database\Seeders;

use App\Models\HeatAlert;
use App\Models\Neighborhood;
use App\Models\PowerOutage;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data for the deployed site. It runs on every start of the deployed container
 * (RUN_DEMO_SEED=true), so a redeploy always leaves the site ready to test:
 *
 *   php artisan db:seed --class=DeployDemoSeeder
 *
 * Safe to run any number of times, on an empty or an already filled database:
 * - the base demo data (accounts, neighborhoods, alerts, cool spots, advice, quizzes, outages)
 *   and the Incident Command Center demo are loaded without duplicates;
 * - the seeded alerts and outages are re-dated around "now", so the ones meant to be active,
 *   upcoming or past still are, however long ago the first deploy was;
 * - a few resident heat spot reports are put back in the "pending" state, so the admin
 *   can test the approve / reject workflow again after each deploy.
 *
 * All demo accounts use the password "password".
 */
class DeployDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DatabaseSeeder::class,
            IncidentCommandCenterDemoSeeder::class,
        ]);

        $this->redateAlerts();
        $this->redateOutages();
        $this->seedReportsToReview();
    }

    /**
     * Put the alerts of HeatAlertSeeder back around today (title => [start offset, end offset]).
     */
    private function redateAlerts(): void
    {
        $alerts = [
            'Alerte Canicule Rouge - Vigilance Absolue' => [now()->subDay(), now()->addDays(3)],
            'Vague de chaleur persistante' => [now()->subHours(5), now()->addDays(2)],
            'Alerte passée: Pic de chaleur de la semaine dernière' => [now()->subDays(10), now()->subDays(7)],
            'Températures chaudes persistantes' => [now()->subDays(2), null],
            'Chaleur intense sur le littoral' => [now()->subHours(2), now()->addDays(2)],
            'Canicule annoncée ce week-end' => [now()->addDay(), now()->addDays(4)],
            'Épisode de chaleur du mois dernier' => [now()->subDays(35), now()->subDays(32)],
        ];

        foreach ($alerts as $title => [$start, $end]) {
            HeatAlert::where('title', $title)->update(['start_date' => $start, 'end_date' => $end]);
        }
    }

    /**
     * Put the outages of PowerOutageSeeder back around today (title => [start, estimated end]).
     */
    private function redateOutages(): void
    {
        $outages = [
            'Main boulevard outage' => [now()->subMinutes(45), now()->addHours(2)],
            'South building maintenance' => [now()->addHours(3), now()->addHours(8)],
            'Network restoration' => [now()->subDays(2), now()->subDay()],
        ];

        foreach ($outages as $title => [$start, $end]) {
            PowerOutage::where('title', $title)->update(['started_at' => $start, 'estimated_end_at' => $end]);
        }
    }

    /**
     * Resident heat spot reports: three are waiting for the admin, one was already approved.
     * A rerun puts the pending ones back to "pending" (an approved or rejected one is reset).
     */
    private function seedReportsToReview(): void
    {
        $reporter = User::where('email', 'amine@heatalert.test')->first();
        $admin = User::where('email', 'admin@heatalert.test')->first();

        if ($reporter === null) {
            return;
        }

        $reports = [
            ['La Marsa', 'Bus stop with no shade', 'No shelter and the metal bench burns in the afternoon.', 'high', 41.0, 36.8790, 10.3200, true],
            ['Ariana', 'Playground surface too hot', 'The rubber floor is too hot for children after noon.', 'medium', 38.0, 36.8700, 10.1700, true],
            ['Centre Ville', 'Water fountain out of order near the market', 'The only drinking fountain of the square no longer works.', 'high', 40.0, 36.7990, 10.1830, true],
            ['Le Lac', 'Shaded walkway open to everyone', 'The covered walkway along the lake stays cool all day.', 'low', 30.0, 36.8330, 10.2280, false],
        ];

        foreach ($reports as [$neighborhoodName, $title, $description, $severity, $temperature, $latitude, $longitude, $pending]) {
            $neighborhood = Neighborhood::where('name', $neighborhoodName)->first();

            if ($neighborhood === null) {
                continue;
            }

            HeatAlert::updateOrCreate(
                ['neighborhood_id' => $neighborhood->id, 'title' => $title],
                [
                    'description' => $description,
                    'severity_level' => $severity,
                    'temperature' => $temperature,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'source' => HeatAlert::SOURCE_COMMUNITY,
                    'review_status' => $pending ? HeatAlert::REVIEW_PENDING : HeatAlert::REVIEW_APPROVED,
                    'reviewed_by' => $pending ? null : $admin?->id,
                    'reviewed_at' => $pending ? null : now(),
                    'created_by' => $reporter->id,
                    'start_date' => now()->subMinutes(30),
                    'end_date' => now()->addHours(12),
                ],
            );
        }
    }
}
