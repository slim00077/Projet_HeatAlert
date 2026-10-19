<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SeedIfEmpty extends Command
{
    protected $signature = 'db:seed-if-empty';

    protected $description = 'Load the demo data (accounts, neighborhoods, alerts...) only when the database has no user yet';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->info('The database already contains data: seeding skipped.');

            return self::SUCCESS;
        }

        $this->info('Empty database: loading the demo data.');

        return $this->call('db:seed', ['--force' => true]);
    }
}
