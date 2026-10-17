<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Development admin account: admin@heatalert.test / password.
     * Safe to run several times; the account is reset to a known state each time.
     */
    public function run(): void
    {
        $admin = User::firstOrNew(['email' => 'admin@heatalert.test']);

        $admin->forceFill([
            'name' => 'HeatAlert Admin',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
        ])->save();
    }
}
