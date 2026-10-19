<?php

use App\Models\CoolSpot;
use App\Models\CoolSpotType;
use App\Models\Neighborhood;
use App\Models\PowerOutage;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('uses the shared OpenStreetMap picker on the neighborhood form', function () {
    $neighborhood = Neighborhood::factory()->create(['latitude' => 36.8123, 'longitude' => 10.1456]);

    $this->actingAs($this->admin)->get(route('admin.neighborhoods.edit', $neighborhood))
        ->assertOk()
        ->assertSee('data-location-picker', false)
        ->assertSee('window.HeatMap', false)
        ->assertSee('name="latitude"', false)
        ->assertSee('data-latitude="36.8123"', false)
        ->assertSee('data-longitude="10.1456"', false)
        ->assertSee('Search a place in Tunisia', false);

    $this->actingAs($this->admin)->get(route('admin.neighborhoods.create'))
        ->assertOk()
        ->assertSee('data-location-picker', false)
        ->assertSee('No location selected.');
});

it('replaces the cool spot map with the shared picker and keeps its address tools', function () {
    $type = CoolSpotType::create(['name' => 'Park']);
    $spot = CoolSpot::create([
        'cool_spot_type_id' => $type->id,
        'name' => 'Central Park',
        'address' => 'Tunis',
        'latitude' => 36.8,
        'longitude' => 10.18,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)->get(route('admin.cool-spots.edit', $spot))
        ->assertOk()
        ->assertSee('data-location-picker', false)
        ->assertSee('data-address-input="#address"', false)
        ->assertSee('Locate address')
        ->assertSee('Use pin address')
        ->assertSee('data-latitude="36.8', false)
        ->assertSee('Generate description with AI')
        ->assertDontSee('cool-spot-map', false);
});

it('offers the same picker to residents proposing a cool spot, without the admin AI button', function () {
    CoolSpotType::create(['name' => 'Park']);

    $this->actingAs(User::factory()->create())->get(route('cool-spots.create'))
        ->assertOk()
        ->assertSee('data-location-picker', false)
        ->assertDontSee('Generate description with AI');
});

it('lets the admin pick the affected neighborhoods on a map when creating an outage', function () {
    $mapped = Neighborhood::factory()->create(['name' => 'Mapped Zone', 'latitude' => 36.8, 'longitude' => 10.2]);
    Neighborhood::factory()->create(['name' => 'Unmapped Zone', 'latitude' => null, 'longitude' => null]);

    $response = $this->actingAs($this->admin)->get(route('admin.power-outages.create'))
        ->assertOk()
        ->assertSee('data-zone-picker', false)
        ->assertSee('neighborhood_ids[]', false)
        ->assertSee('1 neighborhood without a location can only be ticked below');

    $zones = html_entity_decode($response->getContent());
    expect($zones)->toContain('"name":"Mapped Zone"')->and($zones)->not->toContain('"name":"Unmapped Zone"');
    expect($mapped->hasCoordinates())->toBeTrue();
});

it('keeps the neighborhood checkboxes but hides the map when no neighborhood has a location', function () {
    Neighborhood::factory()->create(['name' => 'Lonely Zone', 'latitude' => null, 'longitude' => null]);

    $this->actingAs($this->admin)->get(route('admin.power-outages.create'))
        ->assertOk()
        ->assertDontSee('data-zone-picker', false)
        ->assertSee('Lonely Zone');
});

it('shows the selected neighborhoods when editing an outage', function () {
    $zone = Neighborhood::factory()->create(['latitude' => 36.8, 'longitude' => 10.2]);
    $outage = PowerOutage::factory()->create();
    $outage->neighborhoods()->attach($zone);

    $this->actingAs($this->admin)->get(route('admin.power-outages.edit', $outage))
        ->assertOk()
        ->assertSee('id="neighborhood-'.$zone->id.'"', false)
        ->assertSee('checked', false)
        ->assertSee('data-zone-picker', false);
});

it('loads Leaflet only once per page even with several maps', function () {
    $this->actingAs($this->admin)->get(route('admin.power-outages.index'))
        ->assertOk()
        ->assertSee('Outage management');

    $html = $this->actingAs($this->admin)->get(route('admin.power-outages.create'))->getContent();

    expect(substr_count($html, 'leaflet.js'))->toBe(1)->and(substr_count($html, 'window.HeatMap = '))->toBe(1);
});

it('defines every color variable the outage styles use', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    preg_match_all('/var\(--(color-[a-z-]+)\)/', $css, $used);

    foreach (array_unique($used[1]) as $variable) {
        expect($css)->toContain("--{$variable}:");
    }
});
