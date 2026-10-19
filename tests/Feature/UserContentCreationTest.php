<?php

use App\Models\Advice;
use App\Models\AdviceCategory;
use App\Models\HeatAlert;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('guests cannot open the creation forms', function () {
    $this->get(route('advice.create'))->assertRedirect(route('login'));
    $this->get(route('front.alerts.create'))->assertRedirect(route('login'));
});

test('a resident can add an advice with an image', function () {
    Storage::fake('public');
    $category = AdviceCategory::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('advice.store'), [
            'advice_category_id' => $category->id,
            'risk_level' => 'low',
            'title' => 'Drink water',
            'content' => 'Stay hydrated.',
            'is_approved' => true,
            'image' => UploadedFile::fake()->createWithContent('tip.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('advice', ['title' => 'Drink water', 'is_approved' => false]);
    $this->get(route('advice.index'))->assertDontSee('Drink water');
    Storage::disk('public')->assertExists(Advice::first()->image_path);
});

test('a resident can add a heat alert with a residence name', function () {
    $neighborhood = Neighborhood::create(['name' => 'Centre']);

    $this->actingAs(User::factory()->create())
        ->post(route('front.alerts.store'), [
            'neighborhood_id' => $neighborhood->id,
            'title' => 'Hot day',
            'residence_name' => 'Les Jasmins',
            'severity_level' => 'high',
            'start_date' => now()->subHour()->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect(route('front.alerts.index'));

    expect(HeatAlert::first()->residence_name)->toBe('Les Jasmins');
});
