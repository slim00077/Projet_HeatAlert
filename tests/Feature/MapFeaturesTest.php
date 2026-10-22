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
    $this->marsa = Neighborhood::factory()->create(['name' => 'La Marsa', 'latitude' => 36.8782, 'longitude' => 10.3247]);
    $this->ariana = Neighborhood::factory()->create(['name' => 'Ariana', 'latitude' => 36.8665, 'longitude' => 10.1647]);
    $this->resident = User::factory()->create();
    $this->admin = User::factory()->admin()->create();
});

function spotPayload(array $overrides = []): array
{
    return array_merge([
        'latitude' => 36.88,
        'longitude' => 10.32,
        'title' => 'No shade at the bus stop',
        'severity_level' => 'high',
        'temperature' => 41,
        'duration_hours' => 6,
    ], $overrides);
}

it('shows the danger banner above the navbar for active high alerts only', function () {
    $this->get(route('front.alerts.index'))->assertDontSee('data-danger-banner', false);

    HeatAlert::factory()->for($this->marsa)->severity(Severity::Medium)->create();
    HeatAlert::factory()->for($this->marsa)->severity(Severity::High)->expired()->create();
    HeatAlert::factory()->for($this->marsa)->severity(Severity::High)->create(['source' => 'community']);
    $this->get(route('front.alerts.index'))->assertDontSee('data-danger-banner', false);

    HeatAlert::factory()->for($this->marsa)->severity(Severity::High)->create(['title' => 'Red alert']);

    $this->get('/')->assertSee('data-danger-banner', false)->assertSee('La Marsa')->assertSee('Red alert');
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('data-danger-banner', false);
});

it('hides residences from guests on the map data', function () {
    Residence::factory()->create(['user_id' => $this->resident->id, 'neighborhood' => 'La Marsa']);

    $this->getJson(route('map.data'))
        ->assertOk()
        ->assertJsonPath('neighborhoods.1.residences_count', 1)
        ->assertJsonMissingPath('neighborhoods.1.residences');
});

it('lists only their own residences to a resident and all of them to an admin', function () {
    $mine = Residence::factory()->create(['user_id' => $this->resident->id, 'neighborhood' => 'La Marsa', 'name' => 'My Place']);
    Residence::factory()->create(['user_id' => User::factory()->create()->id, 'neighborhood' => 'La Marsa', 'name' => 'Other Place']);

    $this->actingAs($this->resident)->getJson(route('map.data'))
        ->assertJsonCount(1, 'neighborhoods.1.residences')
        ->assertJsonPath('neighborhoods.1.residences.0.name', 'My Place')
        ->assertJsonPath('neighborhoods.1.residences.0.owner', null);

    $this->actingAs($this->admin)->getJson(route('map.data'))
        ->assertJsonCount(2, 'neighborhoods.1.residences')
        ->assertJsonPath('neighborhoods.1.residences.0.owner', fn ($owner) => $owner !== null);
});

it('returns temperatures and active pins on the map data', function () {
    $this->marsa->forceFill(['current_temperature' => 33.4, 'forecast_max_temperature' => 39.0])->save();
    HeatAlert::factory()->for($this->marsa)->create(['latitude' => 36.88, 'longitude' => 10.32]);
    HeatAlert::factory()->for($this->marsa)->create();

    $this->getJson(route('map.data'))
        ->assertJsonPath('neighborhoods.1.weather.current_temperature', 33.4)
        ->assertJsonCount(1, 'pins')
        ->assertJsonPath('pins.0.latitude', 36.88);
});

it('requires login to report a heat spot', function () {
    $this->postJson(route('map.alerts.store'), spotPayload())->assertUnauthorized();
});

it('lets a resident drop a community report that does not notify or raise the banner', function () {
    Notification::fake();
    Residence::factory()->create(['user_id' => $this->resident->id, 'neighborhood' => 'La Marsa']);

    $this->actingAs($this->resident)->postJson(route('map.alerts.store'), spotPayload())
        ->assertCreated()
        ->assertJsonPath('alert.source', 'community');

    $alert = HeatAlert::sole();
    expect($alert->neighborhood_id)->toBe($this->marsa->id)
        ->and($alert->created_by)->toBe($this->resident->id)
        ->and((float) $alert->latitude)->toBe(36.88)
        ->and($alert->isActive())->toBeTrue();

    Notification::assertNothingSent();
    $this->get('/')->assertDontSee('data-danger-banner', false);
});

it('picks the closest neighborhood for the point', function () {
    $this->actingAs($this->resident)->postJson(route('map.alerts.store'), spotPayload(['latitude' => 36.867, 'longitude' => 10.17]))
        ->assertCreated();

    expect(HeatAlert::sole()->neighborhood_id)->toBe($this->ariana->id);
});

it('rejects points far from every monitored neighborhood', function () {
    $this->actingAs($this->resident)->postJson(route('map.alerts.store'), spotPayload(['latitude' => 48.85, 'longitude' => 2.35]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('latitude');

    expect(HeatAlert::count())->toBe(0);
});

it('validates the report and limits resident duration', function () {
    $this->actingAs($this->resident)->postJson(route('map.alerts.store'), spotPayload(['duration_hours' => 48, 'severity_level' => 'extreme']))
        ->assertJsonValidationErrors(['duration_hours', 'severity_level']);

    $this->actingAs($this->admin)->postJson(route('map.alerts.store'), spotPayload(['duration_hours' => 48]))
        ->assertCreated();
});

it('lets an admin create an official alert from the map that notifies residents and raises the banner', function () {
    Notification::fake();
    Residence::factory()->create(['user_id' => $this->resident->id, 'neighborhood' => 'La Marsa']);

    $this->actingAs($this->admin)->postJson(route('map.alerts.store'), spotPayload())
        ->assertCreated()
        ->assertJsonPath('alert.source', 'manual');

    Notification::assertSentTo($this->resident, HeatAlertIssued::class);
    $this->get('/')->assertSee('data-danger-banner', false);
});

it('renders the map controls on the public and admin alert pages', function () {
    $this->get(route('front.alerts.index'))->assertOk()->assertSee('Report a heat spot')->assertSee(route('map.data'), false);
    $this->actingAs($this->admin)->get(route('admin.alerts.index'))->assertOk()->assertSee(route('map.data'), false)->assertDontSee('id="map-report-toggle"', false);
});

it('finds the nearest neighborhood within range only', function () {
    expect(Neighborhood::nearestTo(36.87, 10.17)->id)->toBe($this->ariana->id)
        ->and(Neighborhood::nearestTo(36.88, 10.32)->id)->toBe($this->marsa->id)
        ->and(Neighborhood::nearestTo(0, 0))->toBeNull();
});
