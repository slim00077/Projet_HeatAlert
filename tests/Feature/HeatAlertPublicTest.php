<?php

use App\Enums\Severity;
use App\Models\HeatAlert;
use App\Models\Neighborhood;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists current and upcoming alerts but not expired ones', function () {
    HeatAlert::factory()->create(['title' => 'Running alert']);
    HeatAlert::factory()->upcoming()->create(['title' => 'Coming alert']);
    HeatAlert::factory()->expired()->create(['title' => 'Old alert']);
    HeatAlert::factory()->openEnded()->create(['title' => 'Open ended alert']);

    $this->get(route('front.alerts.index'))
        ->assertOk()
        ->assertSee('Running alert')
        ->assertSee('Coming alert')
        ->assertSee('Open ended alert')
        ->assertDontSee('Old alert');
});

it('applies the neighborhood filter to open-ended alerts too', function () {
    $marsa = Neighborhood::factory()->create();
    $ariana = Neighborhood::factory()->create();
    HeatAlert::factory()->for($marsa)->severity(Severity::Low)->create(['title' => 'Marsa alert']);
    HeatAlert::factory()->for($ariana)->severity(Severity::Low)->openEnded()->create(['title' => 'Ariana open alert']);

    $this->get(route('front.alerts.index', ['neighborhood_id' => $marsa->id]))
        ->assertSee('Marsa alert')
        ->assertDontSee('Ariana open alert');
});

it('exposes only active alerts through the api', function () {
    $active = HeatAlert::factory()->create(['title' => 'Active API alert']);
    HeatAlert::factory()->expired()->create();
    HeatAlert::factory()->upcoming()->create();

    $this->getJson(route('api.v1.alerts.active'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id)
        ->assertJsonPath('data.0.neighborhood.id', $active->neighborhood_id)
        ->assertJsonStructure(['data' => [['id', 'title', 'severity', 'status', 'recommendations', 'neighborhood' => ['latitude', 'longitude']]]]);
});

it('filters the api by neighborhood', function () {
    $alert = HeatAlert::factory()->create();
    HeatAlert::factory()->create();

    $this->getJson(route('api.v1.alerts.active', ['neighborhood_id' => $alert->neighborhood_id]))
        ->assertJsonCount(1, 'data');
});
