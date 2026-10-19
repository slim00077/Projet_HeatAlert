<?php

use App\Models\Advice;
use App\Models\AdviceCategory;
use App\Models\User;
use Database\Seeders\AdviceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows advice matching a public search and category filter', function () {
    $hydration = AdviceCategory::factory()->create(['name' => 'Hydration']);
    $energy = AdviceCategory::factory()->create(['name' => 'Energy']);
    $hydrationAdvice = Advice::factory()->for($hydration, 'category')->create([
        'title' => 'Drink water often',
    ]);
    $energyAdvice = Advice::factory()->for($energy, 'category')->create([
        'title' => 'Save energy at home',
    ]);

    $this->get(route('advice.index', ['search' => 'water', 'category' => $hydration->id]))
        ->assertSee($hydrationAdvice->title)
        ->assertDontSee($energyAdvice->title);
});

it('shows advice details and escapes submitted content', function () {
    $category = AdviceCategory::factory()->create(['name' => 'Hydration']);
    $advice = Advice::factory()->for($category, 'category')->create([
        'title' => 'Drink water often',
        'content' => '<script>alert(1)</script>',
    ]);
    $relatedAdvice = Advice::factory()->for($category, 'category')->create([
        'title' => 'Keep water nearby',
    ]);
    $otherCategory = AdviceCategory::factory()->create();
    $otherAdvice = Advice::factory()->for($otherCategory, 'category')->create([
        'title' => 'Advice from another category',
    ]);

    $this->get(route('advice.show', $advice))
        ->assertSee($advice->title)
        ->assertSee($category->name)
        ->assertSee($relatedAdvice->title)
        ->assertDontSee($otherAdvice->title)
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('requires administrators to manage advice and categories', function () {
    $category = AdviceCategory::factory()->create();
    $resident = User::factory()->create();

    $this->get(route('admin.advices.index'))->assertRedirect(route('login'));
    $this->actingAs($resident)->get(route('admin.advices.index'))->assertForbidden();
    $this->actingAs($resident)->get(route('admin.advice-categories.index'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.advices.create'))->assertOk();
    $this->get(route('admin.advice-categories.show', $category))->assertOk();
});

it('lets administrators create update and delete advice', function () {
    $admin = User::factory()->admin()->create();
    $category = AdviceCategory::factory()->create(['name' => 'Hydration']);

    $this->actingAs($admin)->post(route('admin.advices.store'), [
        'advice_category_id' => $category->id,
        'title' => 'Drink water often',
        'summary' => 'Choose water during hot days.',
        'content' => 'Keep a bottle of water nearby.',
    ])->assertRedirect();

    $advice = Advice::where('title', 'Drink water often')->firstOrFail();
    $this->assertModelExists($advice);
    expect($advice->category->is($category))->toBeTrue();

    $this->actingAs($admin)->get(route('admin.advices.index'))->assertSee($advice->title);
    $this->actingAs($admin)->get(route('admin.advices.show', $advice))->assertSee($advice->title);
    $this->actingAs($admin)->get(route('admin.advices.edit', $advice))->assertOk();
    $this->actingAs($admin)->put(route('admin.advices.update', $advice), [
        'advice_category_id' => $category->id,
        'title' => 'Drink water regularly',
        'summary' => 'Updated summary.',
        'content' => 'Updated heat-safety advice.',
    ])->assertRedirect(route('admin.advices.show', $advice));

    expect($advice->fresh()->title)->toBe('Drink water regularly');
    expect($advice->fresh()->content)->toBe('Updated heat-safety advice.');

    $this->actingAs($admin)->delete(route('admin.advices.destroy', $advice))
        ->assertRedirect(route('admin.advices.index'));
    $this->assertModelMissing($advice);
});

it('validates required advice fields and an existing category', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.advices.store'), [
        'advice_category_id' => 9999,
    ])->assertSessionHasErrors(['advice_category_id', 'title', 'content']);

    expect(Advice::count())->toBe(0);
});

it('lets administrators manage categories without orphaning advice', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.advice-categories.store'), [
        'name' => 'Hydration',
        'description' => 'Water and hydration guidance.',
    ])->assertRedirect();

    $category = AdviceCategory::where('name', 'Hydration')->firstOrFail();
    $this->assertModelExists($category);
    $this->actingAs($admin)->get(route('admin.advice-categories.index'))->assertSee($category->name);
    $this->actingAs($admin)->get(route('admin.advice-categories.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.advice-categories.show', $category))->assertOk();
    $this->actingAs($admin)->get(route('admin.advice-categories.edit', $category))->assertOk();

    $this->actingAs($admin)->put(route('admin.advice-categories.update', $category), [
        'name' => 'Hydration and water',
        'description' => 'Updated category guidance.',
    ])->assertRedirect(route('admin.advice-categories.show', $category));

    expect($category->fresh()->name)->toBe('Hydration and water');

    $advice = Advice::factory()->for($category, 'category')->create();
    $this->actingAs($admin)->delete(route('admin.advice-categories.destroy', $category))
        ->assertRedirect(route('admin.advice-categories.index'))
        ->assertSessionHasErrors('category');
    $this->assertModelExists($category);
    $this->assertModelExists($advice);

    $advice->delete();
    $this->actingAs($admin)->delete(route('admin.advice-categories.destroy', $category))
        ->assertRedirect(route('admin.advice-categories.index'));
    $this->assertModelMissing($category);
});

it('seeds heat-safety advice idempotently across categories', function () {
    $seeder = app(AdviceSeeder::class);
    $seeder->run();
    $adviceCount = Advice::count();

    $seeder->run();

    expect(AdviceCategory::count())->toBe(5);
    expect($adviceCount)->toBe(10);
    expect(Advice::count())->toBe($adviceCount);
});
