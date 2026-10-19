<?php

use App\Models\Neighborhood;
use App\Models\User;

it('loads the demo accounts when the database is empty', function () {
    expect(User::count())->toBe(0);

    $this->artisan('db:seed-if-empty')->assertSuccessful();

    expect(User::where('email', 'admin@heatalert.test')->first()?->isAdmin())->toBeTrue()
        ->and(User::where('email', 'sami.manager@heatalert.test')->first()?->isManager())->toBeTrue()
        ->and(User::where('email', 'amine@heatalert.test')->exists())->toBeTrue()
        ->and(Neighborhood::count())->toBeGreaterThan(0);
});

it('does not seed twice, so restarting the app never duplicates data', function () {
    $this->artisan('db:seed-if-empty')->assertSuccessful();
    $users = User::count();
    $neighborhoods = Neighborhood::count();

    $this->artisan('db:seed-if-empty')
        ->expectsOutput('The database already contains data: seeding skipped.')
        ->assertSuccessful();

    expect(User::count())->toBe($users)->and(Neighborhood::count())->toBe($neighborhoods);
});

it('leaves existing data alone', function () {
    User::factory()->create(['email' => 'someone@example.com']);

    $this->artisan('db:seed-if-empty')->assertSuccessful();

    expect(User::count())->toBe(1);
});

it('does nothing for a non MySQL connection', function () {
    $this->artisan('db:ensure', ['--database' => 'sqlite'])
        ->expectsOutput('Not a MySQL connection: nothing to do.')
        ->assertSuccessful();
});

it('refuses an unusual database name', function () {
    config(['database.connections.unsafe' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'bad`name; DROP DATABASE x',
        'username' => 'root',
        'password' => '',
    ]]);

    $this->artisan('db:ensure', ['--database' => 'unsafe'])->assertFailed();
});
