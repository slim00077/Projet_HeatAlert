<?php

use App\Models\HeatAlert;
use App\Models\PowerOutage;
use App\Models\User;
use Database\Seeders\DeployDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('loads accounts, alerts and reports to review for the deployed site', function () {
    $this->seed(DeployDemoSeeder::class);

    expect(User::where('email', 'admin@heatalert.test')->value('role'))->toBe(User::ROLE_ADMIN)
        ->and(User::where('role', User::ROLE_MANAGER)->exists())->toBeTrue()
        ->and(User::where('role', User::ROLE_RESIDENT)->exists())->toBeTrue()
        ->and(HeatAlert::approved()->active()->exists())->toBeTrue()
        ->and(HeatAlert::pendingReview()->count())->toBe(3)
        ->and(HeatAlert::pendingReview()->pluck('source')->unique()->all())->toBe([HeatAlert::SOURCE_COMMUNITY]);
});

it('can run again without duplicating anything', function () {
    $this->seed(DeployDemoSeeder::class);
    $counts = [User::count(), HeatAlert::count(), PowerOutage::count()];

    $this->seed(DeployDemoSeeder::class);

    expect([User::count(), HeatAlert::count(), PowerOutage::count()])->toBe($counts);
});

it('brings the demo back to life on a later deploy', function () {
    $this->seed(DeployDemoSeeder::class);

    $red = HeatAlert::where('title', 'Alerte Canicule Rouge - Vigilance Absolue')->sole();
    $red->update(['start_date' => now()->subDays(30), 'end_date' => now()->subDays(27)]);
    PowerOutage::where('title', 'Main boulevard outage')->update(['started_at' => now()->subDays(30), 'estimated_end_at' => now()->subDays(29)]);
    HeatAlert::pendingReview()->first()->markReviewed(HeatAlert::REVIEW_APPROVED, User::where('role', User::ROLE_ADMIN)->first());

    $this->seed(DeployDemoSeeder::class);

    expect($red->refresh()->isActive())->toBeTrue()
        ->and(PowerOutage::where('title', 'Main boulevard outage')->sole()->estimated_end_at->isFuture())->toBeTrue()
        ->and(HeatAlert::pendingReview()->count())->toBe(3);
});
