<?php

use App\Contracts\AiTextGenerator;
use App\Models\Neighborhood;
use App\Models\OutageReport;
use App\Models\PowerOutage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

final class FakeOutageImpactSummaryAi implements AiTextGenerator
{
    public array $prompts = [];

    public function __construct(private readonly ?string $reply) {}

    public function isAvailable(): bool
    {
        return $this->reply !== null;
    }

    public function generate(string $system, string $prompt, int $maxTokens = 1200): ?string
    {
        $this->prompts[] = ['system' => $system, 'prompt' => $prompt, 'max_tokens' => $maxTokens];

        return $this->reply;
    }
}

function fakeOutageImpactSummaryAi(?string $reply): FakeOutageImpactSummaryAi
{
    $fake = new FakeOutageImpactSummaryAi($reply);
    app()->instance(AiTextGenerator::class, $fake);

    return $fake;
}

test('guest and resident users cannot generate an outage impact summary', function () {
    $powerOutage = PowerOutage::factory()->create();

    $this->postJson(route('admin.power-outages.impact-summary', $powerOutage))
        ->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->postJson(route('admin.power-outages.impact-summary', $powerOutage))
        ->assertForbidden();
});

test('admin receives a privacy-filtered AI summary based on outage and neighborhood data', function () {
    Cache::flush();
    $powerOutage = PowerOutage::factory()->create([
        'title' => 'Central district interruption',
        'status' => 'in_progress',
        'severity' => 'high',
    ]);
    $neighborhood = Neighborhood::factory()->create([
        'name' => 'North avenue',
        'population' => 2500,
        'vulnerable_population' => 300,
    ]);
    $powerOutage->neighborhoods()->attach($neighborhood);
    OutageReport::factory()->for($powerOutage)->create([
        'reporter_name' => 'Private Reporter',
        'email' => 'reporter@example.com',
        'description' => 'Power is out near 555-123-4567. Contact reporter@example.com to investigate.',
        'status' => 'verified',
    ]);
    $fake = fakeOutageImpactSummaryAi('Two verified reports describe a local interruption.');

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('admin.power-outages.impact-summary', $powerOutage))
        ->assertOk()
        ->assertJson([
            'text' => 'Two verified reports describe a local interruption.',
            'source' => 'ai',
        ]);

    expect($fake->prompts)->toHaveCount(1)
        ->and($fake->prompts[0]['prompt'])
        ->toContain('Central district interruption')
        ->toContain('North avenue')
        ->toContain('2500')
        ->toContain('[email removed]')
        ->toContain('[phone number removed]')
        ->not->toContain('Private Reporter')
        ->not->toContain('reporter@example.com')
        ->not->toContain('555-123-4567');

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('admin.power-outages.impact-summary', $powerOutage))
        ->assertJsonPath('text', 'Two verified reports describe a local interruption.');

    expect($fake->prompts)->toHaveCount(1);
});

test('uses a deterministic summary when AI is unavailable and does not cache it', function () {
    Cache::flush();
    $powerOutage = PowerOutage::factory()->create([
        'title' => 'Central district interruption',
        'status' => 'in_progress',
        'severity' => 'high',
    ]);
    $neighborhood = Neighborhood::factory()->create([
        'name' => 'North avenue',
        'population' => 2500,
        'vulnerable_population' => 300,
    ]);
    $powerOutage->neighborhoods()->attach($neighborhood);
    OutageReport::factory()->for($powerOutage)->create(['status' => 'pending']);
    fakeOutageImpactSummaryAi(null);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->postJson(route('admin.power-outages.impact-summary', $powerOutage))
        ->assertOk()
        ->assertJsonPath('source', 'rules')
        ->assertJsonPath('text', fn (string $text): bool => str_contains($text, 'North avenue')
            && str_contains($text, '2500')
            && str_contains($text, 'pending: 1')
            && str_contains($text, 'not a verified count'));

    $recoveredAi = fakeOutageImpactSummaryAi('A fresh AI summary.');

    $this->actingAs($admin)
        ->postJson(route('admin.power-outages.impact-summary', $powerOutage))
        ->assertJson(['text' => 'A fresh AI summary.', 'source' => 'ai']);

    expect($recoveredAi->prompts)->toHaveCount(1);
});

test('renders the impact summary control on the admin outage detail page', function () {
    $powerOutage = PowerOutage::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.power-outages.show', $powerOutage))
        ->assertOk()
        ->assertSee('data-outage-impact-summary', false)
        ->assertSee('advisory summary');
});
