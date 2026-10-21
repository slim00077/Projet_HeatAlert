<?php

use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\ResponseCase;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create(['name' => 'Nadia Manager']);
    $this->resident = User::factory()->create();

    $this->residence = Residence::factory()->create(['user_id' => $this->manager->id, 'name' => 'Les Palmiers']);
    $this->pending = Residence::factory()->pending()->create(['user_id' => $this->manager->id, 'name' => 'Les Jasmins']);
    $this->residence->members()->attach($this->resident);

    ResidentReport::factory()->create(['residence_id' => $this->residence->id, 'user_id' => $this->resident->id, 'status' => 'Pending']);
    ResponseCase::factory()->for($this->residence)->create();
});

it('shows managers their residences with status, live counts and filters', function () {
    $this->actingAs($this->manager)->get(route('residences.index'))
        ->assertOk()
        ->assertSee('Managed Residences')
        ->assertSee('Les Palmiers')
        ->assertSee('Les Jasmins')
        ->assertSee('Awaiting approval')
        ->assertSee('1 open', false)
        ->assertSee('1 active case', false)
        ->assertSee('data-rh-filter="pending"', false);
});

it('shows admins every residence with its owner', function () {
    $this->actingAs($this->admin)->get(route('residences.index'))
        ->assertOk()
        ->assertSee('Every residence')
        ->assertSee('Owner: Nadia Manager')
        ->assertSee('Les Jasmins');
});

it('shows residents only their approved residences without staff counters', function () {
    $this->actingAs($this->resident)->get(route('residences.index'))
        ->assertOk()
        ->assertSee('My Residences')
        ->assertSee('Les Palmiers')
        ->assertDontSee('Les Jasmins')
        ->assertDontSee('active case')
        ->assertDontSee('data-rh-filter', false);
});

it('tells the user when a search matches no residence', function () {
    $this->actingAs($this->manager)->get(route('residences.index', ['search' => 'zzz-nothing']))
        ->assertOk()
        ->assertSee('No residence matches your search.');
});
