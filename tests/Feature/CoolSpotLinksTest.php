<?php

use App\Models\CoolSpotType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('cool spot links point to the real pages', function () {
    $this->get('/')->assertSee(route('cool-spots.index'));
    $this->get(route('cool-spots.index'))->assertOk();
});

test('admin sidebar links to the cooling points page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee(route('admin.cool-spots.index'));
    $this->actingAs($admin)->get(route('admin.cool-spots.index'))->assertOk();
});

test('a resident can add a cool spot from the front page', function () {
    $type = CoolSpotType::create(['name' => 'Library']);
    $user = User::factory()->create();

    $this->get(route('cool-spots.create'))->assertRedirect(route('login'));
    $this->actingAs($user)->get(route('cool-spots.create'))->assertOk();
    $this->actingAs($user)->post(route('cool-spots.store'), [
        'cool_spot_type_id' => $type->id,
        'name' => 'Biblio',
        'address' => '1 rue X',
        'is_active' => 1,
    ])->assertRedirect(route('cool-spots.index'));

    $this->assertDatabaseHas('cool_spots', ['name' => 'Biblio', 'is_active' => true]);
});
