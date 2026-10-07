<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'HeatAlert Admin',
            'email' => 'admin@heatalert.test',
        ]);

        User::factory()->create([
            'name' => 'Amine Ben Salah',
            'email' => 'amine@heatalert.test',
        ]);

        User::factory()->create([
            'name' => 'Sarra Trabelsi',
            'email' => 'sarra@heatalert.test',
        ]);

        User::factory()->create([
            'name' => 'Karim Mansouri',
            'email' => 'karim@heatalert.test',
        ]);
    }
}