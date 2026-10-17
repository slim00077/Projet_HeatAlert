<?php

use App\Contracts\AiTextGenerator;
use App\Enums\Severity;
use App\Models\HeatAlert;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

class FakeAiGenerator implements AiTextGenerator
{
    public array $prompts = [];

    public function __construct(private ?string $reply) {}

    public function isAvailable(): bool
    {
        return $this->reply !== null;
    }

    public function generate(string $system, string $prompt, int $maxTokens = 1200): ?string
    {
        $this->prompts[] = $prompt;

        return $this->reply;
    }
}

function fakeAi(?string $reply): FakeAiGenerator
{
    $fake = new FakeAiGenerator($reply);
    app()->instance(AiTextGenerator::class, $fake);

    return $fake;
}

beforeEach(function () {
    Cache::flush();
    $this->zone = Neighborhood::factory()->create(['name' => 'La Marsa', 'current_temperature' => 36.5, 'forecast_max_temperature' => 41.0]);
    HeatAlert::factory()->for($this->zone)->severity(Severity::High)->create(['title' => 'Red alert']);
});

it('returns an AI safety brief built from the zone data and caches it', function () {
    $fake = fakeAi('Stay inside and drink water.');

    $this->getJson(route('advisor.brief', $this->zone))
        ->assertOk()
        ->assertJson(['text' => 'Stay inside and drink water.', 'source' => 'ai']);

    expect($fake->prompts[0])->toContain('La Marsa')->toContain('36.5')->toContain('Red alert');

    $this->getJson(route('advisor.brief', $this->zone))->assertJson(['source' => 'ai']);
    expect($fake->prompts)->toHaveCount(1);
});

it('falls back to rule-based advice when the AI is unavailable', function () {
    fakeAi(null);

    $this->getJson(route('advisor.brief', $this->zone))
        ->assertOk()
        ->assertJsonPath('source', 'rules')
        ->assertJsonPath('text', fn (string $text) => str_contains($text, 'High heat risk in La Marsa'));
});

it('lets only logged-in users ask the advisor and validates the question', function () {
    fakeAi('Check on your neighbours.');

    $this->postJson(route('advisor.ask', $this->zone), ['question' => 'What should I do today?'])->assertUnauthorized();

    $user = User::factory()->create();
    $this->actingAs($user)->postJson(route('advisor.ask', $this->zone), ['question' => 'abc'])->assertJsonValidationErrors('question');
    $this->actingAs($user)->postJson(route('advisor.ask', $this->zone), ['question' => 'What should I do today?'])
        ->assertOk()
        ->assertJson(['text' => 'Check on your neighbours.', 'source' => 'ai']);
});

it('drafts an alert for admins only, with the AI or with a template', function () {
    $admin = User::factory()->admin()->create();
    $payload = ['neighborhood_id' => $this->zone->id, 'severity_level' => 'high', 'temperature' => 42];

    $this->actingAs(User::factory()->create())->postJson(route('admin.alerts.draft'), $payload)->assertForbidden();

    fakeAi("TITLE: Extreme heat in La Marsa\nDESCRIPTION: Stay indoors until the evening.");
    $this->actingAs($admin)->postJson(route('admin.alerts.draft'), $payload)
        ->assertOk()
        ->assertJson(['title' => 'Extreme heat in La Marsa', 'description' => 'Stay indoors until the evening.', 'source' => 'ai']);

    fakeAi(null);
    $this->actingAs($admin)->postJson(route('admin.alerts.draft'), $payload)
        ->assertJson(['title' => 'High heat alert - La Marsa', 'source' => 'rules']);

    $this->actingAs($admin)->postJson(route('admin.alerts.draft'), ['neighborhood_id' => 999, 'severity_level' => 'x'])
        ->assertJsonValidationErrors(['neighborhood_id', 'severity_level']);
});

it('renders the AI controls on the pages', function () {
    $this->get(route('front.alerts.index'))->assertSee('data-ai-brief', false)->assertSee('advisor/neighborhoods', false);
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.alerts.create'))->assertSee('Draft title and description with AI');
});
