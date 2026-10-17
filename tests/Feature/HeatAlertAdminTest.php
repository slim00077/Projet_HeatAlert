<?php

use App\Enums\Severity;
use App\Models\HeatAlert;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->neighborhood = Neighborhood::factory()->create();
});

function alertPayload(Neighborhood $neighborhood, array $overrides = []): array
{
    return array_merge([
        'neighborhood_id' => $neighborhood->id,
        'title' => 'Heat wave',
        'description' => 'Very hot.',
        'severity_level' => 'high',
        'temperature' => 41.5,
        'start_date' => now()->addDay()->format('Y-m-d\TH:i'),
        'end_date' => now()->addDays(3)->format('Y-m-d\TH:i'),
    ], $overrides);
}

it('forbids residents and guests from managing alerts', function () {
    $this->get(route('admin.alerts.index'))->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get(route('admin.alerts.index'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('admin.neighborhoods.index'))->assertForbidden();
});

it('creates, shows, updates and deletes an alert', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.alerts.store'), alertPayload($this->neighborhood))
        ->assertRedirect();

    $alert = HeatAlert::firstOrFail();
    expect($alert->severity_level)->toBe(Severity::High);

    $this->actingAs($this->admin)->get(route('admin.alerts.show', $alert))->assertOk()->assertSee('Heat wave');
    $this->actingAs($this->admin)->get(route('admin.alerts.edit', $alert))->assertOk();

    $this->actingAs($this->admin)
        ->put(route('admin.alerts.update', $alert), alertPayload($this->neighborhood, ['title' => 'Updated']))
        ->assertRedirect(route('admin.alerts.show', $alert));
    expect($alert->fresh()->title)->toBe('Updated');

    $this->actingAs($this->admin)->delete(route('admin.alerts.destroy', $alert))->assertRedirect(route('admin.alerts.index'));
    expect(HeatAlert::count())->toBe(0);
});

it('validates the alert payload', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.alerts.store'), alertPayload($this->neighborhood, [
            'severity_level' => 'extreme',
            'end_date' => now()->subDays(5)->format('Y-m-d\TH:i'),
        ]))
        ->assertSessionHasErrors(['severity_level', 'end_date']);
});

it('rejects an overlapping alert of the same severity in the same neighborhood', function () {
    HeatAlert::factory()->severity(Severity::High)->for($this->neighborhood)->create();

    $this->actingAs($this->admin)
        ->post(route('admin.alerts.store'), alertPayload($this->neighborhood, [
            'start_date' => now()->format('Y-m-d\TH:i'),
            'end_date' => now()->addDay()->format('Y-m-d\TH:i'),
        ]))
        ->assertSessionHasErrors('start_date');

    $this->actingAs($this->admin)
        ->post(route('admin.alerts.store'), alertPayload($this->neighborhood, ['severity_level' => 'low']))
        ->assertSessionDoesntHaveErrors();
});

it('allows an alert to be edited without clashing with itself', function () {
    $alert = HeatAlert::factory()->severity(Severity::High)->for($this->neighborhood)->create();

    $this->actingAs($this->admin)
        ->put(route('admin.alerts.update', $alert), alertPayload($this->neighborhood, [
            'start_date' => $alert->start_date->format('Y-m-d\TH:i'),
            'end_date' => $alert->end_date->format('Y-m-d\TH:i'),
        ]))
        ->assertSessionDoesntHaveErrors();
});

it('filters the alert list by status, severity and neighborhood', function () {
    HeatAlert::factory()->for($this->neighborhood)->severity(Severity::Medium)->create(['title' => 'Active one']);
    HeatAlert::factory()->for($this->neighborhood)->severity(Severity::Low)->expired()->create(['title' => 'Expired one']);
    HeatAlert::factory()->severity(Severity::Low)->create(['title' => 'Other place']);

    $this->actingAs($this->admin)->get(route('admin.alerts.index', ['status' => 'active']))
        ->assertSee('Active one')->assertDontSee('Expired one');

    $this->actingAs($this->admin)->get(route('admin.alerts.index', ['neighborhood_id' => $this->neighborhood->id]))
        ->assertSee('Active one')->assertDontSee('Other place');

    $this->actingAs($this->admin)->get(route('admin.alerts.index', ['severity' => 'low']))
        ->assertSee('Other place')->assertDontSee('Active one');
});

it('manages neighborhoods and enforces unique names', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.neighborhoods.store'), ['name' => 'La Marsa', 'latitude' => 36.87, 'longitude' => 10.32, 'population' => 1000, 'vulnerable_population' => 100])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('admin.neighborhoods.store'), ['name' => 'La Marsa'])
        ->assertSessionHasErrors('name');

    $neighborhood = Neighborhood::where('name', 'La Marsa')->firstOrFail();
    $this->actingAs($this->admin)->get(route('admin.neighborhoods.show', $neighborhood))->assertOk();
    $this->actingAs($this->admin)
        ->put(route('admin.neighborhoods.update', $neighborhood), ['name' => 'La Marsa']) // own name is fine
        ->assertSessionDoesntHaveErrors();
});

it('shows the current risk of a neighborhood from its active alerts', function () {
    HeatAlert::factory()->for($this->neighborhood)->severity(Severity::Medium)->create();
    HeatAlert::factory()->for($this->neighborhood)->severity(Severity::High)->create();
    HeatAlert::factory()->for($this->neighborhood)->severity(Severity::High)->expired()->create();

    expect($this->neighborhood->load('activeAlerts')->currentRisk())->toBe(Severity::High);
    expect(Neighborhood::factory()->create()->load('activeAlerts')->currentRisk())->toBeNull();
});

it('renders the dashboard with alert statistics', function () {
    HeatAlert::factory()->for($this->neighborhood)->severity(Severity::High)->create();

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Active Alerts')
        ->assertSee($this->neighborhood->name);
});

it('suggests a severity from a temperature', function () {
    expect(Severity::fromTemperature(30))->toBe(Severity::Low)
        ->and(Severity::fromTemperature(36))->toBe(Severity::Medium)
        ->and(Severity::fromTemperature(40))->toBe(Severity::High);
});
