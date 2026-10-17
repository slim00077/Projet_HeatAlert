<?php

use App\Enums\Severity;
use App\Models\HeatAlert;
use App\Models\Neighborhood;
use App\Models\Residence;
use App\Models\User;
use App\Notifications\HeatAlertIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function fakeForecast(float $current, array $maximums): void
{
    $dates = collect($maximums)->keys()->map(fn (int $offset) => now()->addDays($offset)->format('Y-m-d'))->all();

    Http::swap(new Factory);
    Http::fake([
        'api.open-meteo.com/*' => Http::response([
            'current' => ['temperature_2m' => $current],
            'daily' => ['time' => $dates, 'temperature_2m_max' => array_values($maximums)],
        ]),
    ]);
}

beforeEach(function () {
    $this->neighborhood = Neighborhood::factory()->create(['name' => 'La Marsa']);
});

it('stores the weather and creates an automatic alert for a heatwave forecast', function () {
    Notification::fake();
    fakeForecast(34.2, [38.0, 41.5, 36.0]);

    $this->artisan('weather:sync')->assertSuccessful();

    $neighborhood = $this->neighborhood->fresh();
    expect($neighborhood->current_temperature)->toBe(34.2)
        ->and($neighborhood->forecast_max_temperature)->toBe(41.5)
        ->and($neighborhood->weather_updated_at)->not->toBeNull();

    $alert = HeatAlert::sole();
    expect($alert->source)->toBe(HeatAlert::SOURCE_AUTO)
        ->and($alert->severity_level)->toBe(Severity::High)
        ->and((float) $alert->temperature)->toBe(41.5)
        ->and($alert->isActive())->toBeTrue();

    Http::assertSent(fn ($request) => $request['latitude'] == $neighborhood->latitude && $request['forecast_days'] == 3);
});

it('does not create an alert for a mild forecast', function () {
    fakeForecast(24.0, [27.0, 29.0, 31.0]);

    $this->artisan('weather:sync')->assertSuccessful();

    expect(HeatAlert::count())->toBe(0)
        ->and($this->neighborhood->fresh()->forecast_max_temperature)->toBe(31.0);
});

it('updates the same automatic alert instead of duplicating it', function () {
    Notification::fake();
    fakeForecast(30.0, [36.0, 36.0, 30.0]);
    $this->artisan('weather:sync');

    fakeForecast(30.0, [36.0, 42.0, 41.0]);
    $this->artisan('weather:sync');

    $alert = HeatAlert::sole();
    expect($alert->severity_level)->toBe(Severity::High);
});

it('notifies residents again when the severity escalates', function () {
    Notification::fake();
    $resident = User::factory()->create();
    Residence::factory()->create(['user_id' => $resident->id, 'neighborhood' => 'La Marsa']);

    fakeForecast(33.0, [36.0, 30.0, 30.0]);
    $this->artisan('weather:sync');
    Notification::assertSentToTimes($resident, HeatAlertIssued::class, 1);

    $this->artisan('weather:sync');
    Notification::assertSentToTimes($resident, HeatAlertIssued::class, 1);

    fakeForecast(38.0, [41.0, 30.0, 30.0]);
    $this->artisan('weather:sync');
    Notification::assertSentToTimes($resident, HeatAlertIssued::class, 2);
});

it('closes the active automatic alert when the heat is over', function () {
    Notification::fake();
    fakeForecast(33.0, [37.0, 30.0, 30.0]);
    $this->artisan('weather:sync');

    fakeForecast(25.0, [26.0, 27.0, 28.0]);
    $this->artisan('weather:sync');

    expect(HeatAlert::sole()->status())->toBe(HeatAlert::STATUS_EXPIRED);
});

it('never touches manual alerts', function () {
    Notification::fake();
    $manual = HeatAlert::factory()->for($this->neighborhood)->create();

    fakeForecast(25.0, [26.0, 27.0, 28.0]);
    $this->artisan('weather:sync');

    expect($manual->fresh()->end_date->isFuture())->toBeTrue();
});

it('survives a weather service outage', function () {
    Http::fake(['api.open-meteo.com/*' => Http::response('down', 500)]);

    $this->artisan('weather:sync')->assertSuccessful()->expectsOutput('Weather refreshed for 0 neighborhood(s).');

    expect($this->neighborhood->fresh()->weather_updated_at)->toBeNull();
});

it('skips neighborhoods without coordinates', function () {
    Http::fake();
    Neighborhood::query()->update(['latitude' => null, 'longitude' => null]);

    $this->artisan('weather:sync')->assertSuccessful();

    Http::assertNothingSent();
});

it('lets an admin refresh the weather from the alerts page', function () {
    Notification::fake();
    fakeForecast(30.0, [31.0, 32.0, 33.0]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.weather.sync'))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs(User::factory()->create())->post(route('admin.weather.sync'))->assertForbidden();
});

it('exposes every neighborhood with its details on the map api', function () {
    $this->neighborhood->forceFill(['current_temperature' => 33.1])->save();
    HeatAlert::factory()->for($this->neighborhood)->severity(Severity::High)->create();
    Neighborhood::factory()->create(['name' => 'Calm', 'latitude' => null, 'longitude' => null]);
    $quiet = Neighborhood::factory()->create(['name' => 'Quiet']);

    $response = $this->getJson(route('api.v1.neighborhoods.index'))->assertOk();

    $response->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'La Marsa')
        ->assertJsonPath('data.0.risk', 'high')
        ->assertJsonPath('data.0.weather.current_temperature', 33.1)
        ->assertJsonCount(1, 'data.0.active_alerts')
        ->assertJsonPath('data.1.id', $quiet->id)
        ->assertJsonPath('data.1.risk', 'none');
});

it('renders the neighborhood map on the public and admin alert pages', function () {
    $this->get(route('front.alerts.index'))->assertOk()->assertSee('neighborhood-map', false);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.alerts.index'))->assertOk()->assertSee('neighborhood-map', false)->assertSee('Refresh weather');
});
