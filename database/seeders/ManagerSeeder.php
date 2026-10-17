<?php

namespace Database\Seeders;

use App\Models\Neighborhood;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Database\Seeder;

class ManagerSeeder extends Seeder
{
    /**
     * Demo residence managers (password "password"), each responsible for one neighborhood,
     * with an approved residence (with residents), a pending one and a rejected one.
     * Needs the neighborhoods from HeatAlertSeeder. Safe to run several times.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@heatalert.test')->first();

        $managers = [
            ['Sami Gharbi', 'sami.manager@heatalert.test', 'La Marsa'],
            ['Leila Hammami', 'leila.manager@heatalert.test', 'Ariana'],
        ];

        foreach ($managers as [$name, $email, $neighborhoodName]) {
            $neighborhood = Neighborhood::where('name', $neighborhoodName)->first();

            if ($neighborhood === null) {
                continue;
            }

            $manager = User::firstOrNew(['email' => $email]);
            $manager->forceFill([
                'name' => $name,
                'password' => 'password',
                'role' => User::ROLE_MANAGER,
                'neighborhood_id' => $neighborhood->id,
                'email_verified_at' => now(),
            ])->save();

            $residences = [
                ['Residence Les Dunes', '3 Rue de la Corniche', Residence::STATUS_APPROVED, null],
                ['Residence Sidi Daoud', '58 Avenue de la Plage', Residence::STATUS_PENDING, null],
                ['Residence Nord', '9 Rue des Jardins', Residence::STATUS_REJECTED, 'The address could not be verified. Please check it and resubmit.'],
            ];

            foreach ($residences as [$residenceName, $address, $status, $reason]) {
                Residence::firstOrCreate(
                    ['user_id' => $manager->id, 'name' => "{$residenceName} ({$neighborhoodName})"],
                    [
                        'address' => $address,
                        'neighborhood' => $neighborhoodName,
                        'description' => "Managed by {$name}.",
                        'status' => $status,
                        'rejection_reason' => $reason,
                        'reviewed_by' => $status === Residence::STATUS_PENDING ? null : $admin?->id,
                        'reviewed_at' => $status === Residence::STATUS_PENDING ? null : now(),
                    ],
                );
            }

            $approved = Residence::where('user_id', $manager->id)->approved()->first();
            $members = User::whereIn('email', ['amine@heatalert.test', 'sarra@heatalert.test'])->pluck('id');
            $approved?->members()->syncWithoutDetaching($members);
        }
    }
}
