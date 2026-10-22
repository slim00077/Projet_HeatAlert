<?php

use App\Contracts\AiTextGenerator;
use App\Models\CoolSpot;
use App\Models\CoolSpotType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

class CoolSpotFeatureFakeAiGenerator implements AiTextGenerator
{
    public function __construct(private readonly ?string $reply, private readonly bool $throws = false) {}

    public function isAvailable(): bool
    {
        return $this->reply !== null;
    }

    public function generate(string $system, string $prompt, int $maxTokens = 1200): ?string
    {
        if ($this->throws) {
            throw new RuntimeException('Simulated AI failure.');
        }

        return $this->reply;
    }
}

it('sorts active cool spots by distance while preserving the list filters', function () {
    $type = CoolSpotType::create(['name' => 'Library']);
    $nearby = CoolSpot::create([
        'cool_spot_type_id' => $type->id,
        'name' => 'Nearby Library',
        'address' => 'Tunis',
        'latitude' => 36.8,
        'longitude' => 10.18,
        'is_accessible' => true,
    ]);
    $farther = CoolSpot::create([
        'cool_spot_type_id' => $type->id,
        'name' => 'Farther Library',
        'address' => 'Tunis',
        'latitude' => 37.8,
        'longitude' => 10.18,
        'is_accessible' => true,
    ]);
    CoolSpot::create([
        'cool_spot_type_id' => $type->id,
        'name' => 'Unlocated Library',
        'address' => 'Tunis',
        'is_accessible' => true,
    ]);
    CoolSpot::create([
        'cool_spot_type_id' => $type->id,
        'name' => 'Inactive Library',
        'address' => 'Tunis',
        'latitude' => 36.81,
        'longitude' => 10.18,
        'is_accessible' => true,
        'is_active' => false,
    ]);
    CoolSpot::create([
        'cool_spot_type_id' => $type->id,
        'name' => 'Inaccessible Library',
        'address' => 'Tunis',
        'latitude' => 36.801,
        'longitude' => 10.18,
        'is_accessible' => false,
    ]);

    $response = $this->get(route('cool-spots.index', [
        'lat' => 36.8,
        'lng' => 10.18,
        'type' => $type->id,
        'accessible' => 1,
        'q' => 'Library',
    ]));

    $response->assertSeeInOrder(['Nearby Library', 'Farther Library', 'Unlocated Library'])
        ->assertSee('0.0 km away')
        ->assertSee('111.2 km away')
        ->assertDontSee('Inactive Library')
        ->assertDontSee('Inaccessible Library');

    expect($nearby->exists)->toBeTrue()->and($farther->exists)->toBeTrue();
});

it('reports whether a cool spot is open in Tunis local time', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-19 18:59:00', 'Africa/Tunis'));
    $spot = new CoolSpot(['opening_hours' => '08:00 - 19:00']);
    expect($spot->isOpenNow())->toBeTrue();

    $this->travelTo(CarbonImmutable::parse('2026-10-19 19:00:00', 'Africa/Tunis'));
    expect($spot->isOpenNow())->toBeFalse();
});

it('returns null when cool spot opening hours are absent or unreadable', function (?string $hours) {
    $spot = new CoolSpot(['opening_hours' => $hours]);

    expect($spot->isOpenNow())->toBeNull();
})->with([null, '', 'weekdays only', '25:00 - 27:00']);

it('validates the smart recommendation situation and coordinates', function () {
    $user = User::factory()->create();

    $this->postJson(route('cool-spots.recommend'), ['situation' => 'hot', 'lat' => 91, 'lng' => 181])
        ->assertUnauthorized();

    $this->actingAs($user)
        ->postJson(route('cool-spots.recommend'), ['situation' => 'hot', 'lat' => 91, 'lng' => 181])
        ->assertJsonValidationErrors(['situation', 'lat', 'lng']);
});

it('rejects invalid coordinates on the near me listing request', function () {
    $this->getJson(route('cool-spots.index', ['lat' => 90.01, 'lng' => -181]))
        ->assertJsonValidationErrors(['lat', 'lng']);
});

it('falls back to rule-based recommendations when no AI key is available', function () {
    $type = CoolSpotType::create(['name' => 'Library']);
    $accessibleOpen = CoolSpot::create([
        'cool_spot_type_id' => $type->id,
        'name' => 'Accessible open library',
        'address' => 'Tunis',
        'latitude' => 37.0,
        'longitude' => 10.18,
        'opening_hours' => '08:00 - 19:00',
        'is_accessible' => true,
    ]);
    CoolSpot::create([
        'cool_spot_type_id' => $type->id,
        'name' => 'Nearby inaccessible library',
        'address' => 'Tunis',
        'latitude' => 36.801,
        'longitude' => 10.18,
        'opening_hours' => '08:00 - 19:00',
        'is_accessible' => false,
    ]);
    CoolSpot::create([
        'cool_spot_type_id' => $type->id,
        'name' => 'Accessible closed library',
        'address' => 'Tunis',
        'latitude' => 37.1,
        'longitude' => 10.18,
        'opening_hours' => '06:00 - 08:00',
        'is_accessible' => true,
    ]);
    app()->instance(AiTextGenerator::class, new CoolSpotFeatureFakeAiGenerator(null));
    $this->travelTo(CarbonImmutable::parse('2026-10-19 12:00:00', 'Africa/Tunis'));

    $this->actingAs(User::factory()->create())
        ->postJson(route('cool-spots.recommend'), [
            'situation' => 'I use a wheelchair, it is very hot, and I have no car.',
            'lat' => 36.8,
            'lng' => 10.18,
        ])
        ->assertOk()
        ->assertJsonPath('source', 'rules')
        ->assertJsonPath('recommendations.0.id', $accessibleOpen->id)
        ->assertJsonCount(3, 'recommendations');
});

it('falls back without exposing errors when AI generation throws', function () {
    $type = CoolSpotType::create(['name' => 'Library']);
    CoolSpot::create([
        'cool_spot_type_id' => $type->id,
        'name' => 'National Library',
        'address' => 'Tunis',
        'opening_hours' => '08:00 - 19:00',
    ]);
    app()->instance(AiTextGenerator::class, new CoolSpotFeatureFakeAiGenerator(null, true));

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('cool-spots.recommend'), ['situation' => 'I need a quiet place.'])
        ->assertOk()
        ->assertJsonPath('source', 'rules');

    expect($response->getContent())->not->toContain('Simulated AI failure.');
});

it('ignores unknown AI recommendation ids and strips JSON fences', function () {
    $type = CoolSpotType::create(['name' => 'Library']);
    $spot = CoolSpot::create([
        'cool_spot_type_id' => $type->id,
        'name' => 'National Library',
        'address' => 'Tunis',
        'opening_hours' => '08:00 - 19:00',
    ]);
    app()->instance(AiTextGenerator::class, new CoolSpotFeatureFakeAiGenerator(
        "```json\n[{\"id\":999,\"reason\":\"Unknown point\"},{\"id\":{$spot->id},\"reason\":\"A quiet indoor option.\"}]\n```"
    ));

    $this->actingAs(User::factory()->create())
        ->postJson(route('cool-spots.recommend'), ['situation' => 'I need a quiet place.'])
        ->assertOk()
        ->assertJsonPath('source', 'ai')
        ->assertJsonPath('recommendations.0.id', $spot->id)
        ->assertJsonCount(1, 'recommendations');
});

it('generates a rule-based description when the AI key is unavailable', function () {
    app()->instance(AiTextGenerator::class, new CoolSpotFeatureFakeAiGenerator(null));

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('admin.cool-spots.generate-description'), [
            'name' => 'Belvedere Park',
            'type' => 'Park',
            'address' => 'Tunis',
            'is_accessible' => true,
        ])
        ->assertOk()
        ->assertJsonPath('source', 'rules')
        ->assertJsonPath('description', fn (string $description) => str_contains($description, 'Belvedere Park') && str_contains($description, 'accessible'));
});

it('forbids residents from generating admin descriptions', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('admin.cool-spots.generate-description'), [])
        ->assertForbidden();
});

it('uses the existing AI generator for admin descriptions', function () {
    app()->instance(AiTextGenerator::class, new CoolSpotFeatureFakeAiGenerator('A public cooling point in central Tunis. It offers a place to rest during hot weather.'));

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('admin.cool-spots.generate-description'), [
            'name' => 'City Library',
            'type' => 'Library',
            'address' => 'Tunis',
            'is_accessible' => true,
        ])
        ->assertOk()
        ->assertJson([
            'source' => 'ai',
            'description' => 'A public cooling point in central Tunis. It offers a place to rest during hot weather.',
        ]);
});

it('uses the safe description template when AI output is not 2 to 3 sentences', function () {
    app()->instance(AiTextGenerator::class, new CoolSpotFeatureFakeAiGenerator('A short incomplete description.'));

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('admin.cool-spots.generate-description'), [
            'name' => 'City Library',
            'type' => 'Library',
            'address' => 'Tunis',
        ])
        ->assertOk()
        ->assertJsonPath('source', 'rules')
        ->assertJsonPath('description', fn (string $description) => str_contains($description, 'City Library'));
});
