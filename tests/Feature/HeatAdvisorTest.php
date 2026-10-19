<?php

use App\Contracts\AiTextGenerator;
use App\Enums\Severity;
use App\Models\Advice;
use App\Models\AdviceCategory;
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
    $fake = fakeAi('Check on your neighbours.');
    $category = AdviceCategory::factory()->create(['name' => 'Health and wellbeing']);
    Advice::factory()->for($category, 'category')->create([
        'title' => 'Check on older neighbours',
        'summary' => 'Visit or contact older neighbours twice a day.',
        'risk_level' => Severity::High,
    ]);
    Advice::factory()->for($category, 'category')->create([
        'title' => 'Pending advice should not be shared',
        'risk_level' => Severity::High,
        'is_approved' => false,
    ]);
    Advice::factory()->for($category, 'category')->create([
        'title' => 'Advice for low risk only',
        'risk_level' => Severity::Low,
    ]);

    $this->postJson(route('advisor.ask', $this->zone), ['question' => 'What should I do today?'])->assertUnauthorized();

    $user = User::factory()->create();
    $this->actingAs($user)->postJson(route('advisor.ask', $this->zone), ['question' => 'abc'])->assertJsonValidationErrors('question');
    $this->actingAs($user)->postJson(route('advisor.ask', $this->zone), ['question' => 'What should I do today?'])
        ->assertOk()
        ->assertJson(['text' => 'Check on your neighbours.', 'source' => 'ai']);
    expect($fake->prompts[0])
        ->toContain('Check on older neighbours')
        ->not->toContain('Pending advice should not be shared')
        ->not->toContain('Advice for low risk only');
});

it('does not ask AI to answer when no approved advice matches the current risk', function () {
    $fake = fakeAi('This answer must not be used.');
    $category = AdviceCategory::factory()->create();
    Advice::factory()->for($category, 'category')->create([
        'title' => 'Pending high-risk advice',
        'risk_level' => Severity::High,
        'is_approved' => false,
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('advisor.ask', $this->zone), ['question' => 'How do I help my grandfather?'])
        ->assertOk()
        ->assertJsonPath('source', 'rules')
        ->assertJsonPath('text', fn (string $text) => str_contains($text, 'no administrator-approved advice'));

    expect($fake->prompts)->toBeEmpty();
});

it('returns approved matching advice when the AI service is unavailable', function () {
    fakeAi(null);
    $category = AdviceCategory::factory()->create(['name' => 'Health and wellbeing']);
    Advice::factory()->for($category, 'category')->create([
        'title' => 'Keep older neighbours safe',
        'summary' => 'Check on older neighbours twice a day.',
        'risk_level' => Severity::High,
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('advisor.ask', $this->zone), ['question' => 'How can I help an older neighbour?'])
        ->assertOk()
        ->assertJsonPath('source', 'advice')
        ->assertJsonPath('text', 'Keep older neighbours safe: Check on older neighbours twice a day.');
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

it('does not cache the rule-based fallback so a recovered AI is used right away', function () {
    fakeAi(null);
    $this->getJson(route('advisor.brief', $this->zone))->assertJsonPath('source', 'rules');

    fakeAi('Fresh AI advice.');
    $this->getJson(route('advisor.brief', $this->zone))->assertJson(['text' => 'Fresh AI advice.', 'source' => 'ai']);
});
