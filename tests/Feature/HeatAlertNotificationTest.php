<?php

use App\Enums\Severity;
use App\Models\HeatAlert;
use App\Models\Neighborhood;
use App\Models\Residence;
use App\Models\User;
use App\Notifications\HeatAlertIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    $this->neighborhood = Neighborhood::factory()->create(['name' => 'La Marsa']);
    $this->resident = User::factory()->create();
    $this->neighbor = User::factory()->create();
    $this->elsewhere = User::factory()->create();
    Residence::factory()->create(['user_id' => $this->resident->id, 'neighborhood' => 'La Marsa']);
    Residence::factory()->create(['user_id' => $this->neighbor->id, 'neighborhood' => 'La Marsa']);
    Residence::factory()->create(['user_id' => $this->elsewhere->id, 'neighborhood' => 'Ariana']);
    $this->admin = User::factory()->admin()->create();
});

it('notifies the residents of the neighborhood when an active high alert is created', function () {
    $this->actingAs($this->admin)->post(route('admin.alerts.store'), [
        'neighborhood_id' => $this->neighborhood->id,
        'title' => 'Red alert',
        'severity_level' => 'high',
        'start_date' => now()->subMinute()->format('Y-m-d\TH:i'),
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo([$this->resident, $this->neighbor], HeatAlertIssued::class);
    Notification::assertNotSentTo($this->elsewhere, HeatAlertIssued::class);
    expect(HeatAlert::first()->notified_at)->not->toBeNull();
});

it('does not notify for low severity or future alerts', function () {
    HeatAlert::factory()->for($this->neighborhood)->severity(Severity::Low)->create();
    $future = HeatAlert::factory()->for($this->neighborhood)->severity(Severity::High)->upcoming()->create();

    $this->actingAs($this->admin)->post(route('admin.alerts.store'), [
        'neighborhood_id' => $this->neighborhood->id,
        'title' => 'Later',
        'severity_level' => 'medium',
        'start_date' => now()->addDays(10)->format('Y-m-d\TH:i'),
    ]);

    Notification::assertNothingSent();
    expect($future->fresh()->notified_at)->toBeNull();
});

it('notifies pending alerts once through the scheduled command', function () {
    $alert = HeatAlert::factory()->for($this->neighborhood)->severity(Severity::High)->create();
    HeatAlert::factory()->for($this->neighborhood)->severity(Severity::Low)->create();

    $this->artisan('alerts:notify')->assertSuccessful();
    Notification::assertSentToTimes($this->resident, HeatAlertIssued::class, 1);

    $this->artisan('alerts:notify')->assertSuccessful();
    Notification::assertSentToTimes($this->resident, HeatAlertIssued::class, 1);
    expect($alert->fresh()->notified_at)->not->toBeNull();
});

it('builds a mail with recommendations', function () {
    $alert = HeatAlert::factory()->for($this->neighborhood)->severity(Severity::High)->create();

    $mail = (new HeatAlertIssued($alert->load('neighborhood')))->toMail($this->resident);

    expect($mail->subject)->toContain('La Marsa')
        ->and(collect($mail->introLines)->join(' '))->toContain('Stay indoors');
});
