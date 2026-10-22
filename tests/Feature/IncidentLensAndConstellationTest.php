<?php

use App\Contracts\AiEmbedder;
use App\Contracts\AiTextGenerator;
use App\Contracts\AiVisionGenerator;
use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\ResponseCase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Fake multimodal provider: text and image answers come from a closure, embeddings are keyword-based
 * (cooling / power / water axes) so related reports are close and unrelated ones far apart.
 */
class FakeMultimodalAi implements AiEmbedder, AiTextGenerator, AiVisionGenerator
{
    /** @var list<array{prompt: string, image: ?string, mime: ?string}> */
    public array $calls = [];

    public int $embedCalls = 0;

    public function __construct(public ?Closure $reply) {}

    public function isAvailable(): bool
    {
        return true;
    }

    public function usedModel(): string
    {
        return 'fake-gemini';
    }

    public function generate(string $system, string $prompt, int $maxTokens = 1200): ?string
    {
        $this->calls[] = ['prompt' => $prompt, 'image' => null, 'mime' => null];

        return $this->reply ? ($this->reply)($prompt) : null;
    }

    public function generateWithImage(string $system, string $prompt, string $imageBytes, string $mimeType, int $maxTokens = 1200): ?string
    {
        $this->calls[] = ['prompt' => $prompt, 'image' => $imageBytes, 'mime' => $mimeType];

        return $this->reply ? ($this->reply)($prompt) : null;
    }

    public function embeddingModel(): string
    {
        return 'fake-embedding';
    }

    public function embed(array $texts): ?array
    {
        $this->embedCalls++;

        return array_map(fn (string $text) => [
            (float) str_contains(strtolower($text), 'cool'),
            (float) str_contains(strtolower($text), 'power'),
            (float) str_contains(strtolower($text), 'water'),
            0.05,
        ], $texts);
    }
}

/**
 * A real PNG built without GD (not installed here), so image validation runs on genuine image bytes.
 */
function fakePhoto(string $name = 'vent.png', int $width = 64, int $height = 64): UploadedFile
{
    $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    $rows = str_repeat("\0".str_repeat("\x3b\x82\xf6", $width), $height);
    $png = "\x89PNG\r\n\x1a\n"
        .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
        .$chunk('IDAT', gzcompress($rows))
        .$chunk('IEND', '');

    return UploadedFile::fake()->createWithContent($name, $png);
}

function multimodalAi(?Closure $reply = null): FakeMultimodalAi
{
    $fake = new FakeMultimodalAi($reply);
    app()->instance(AiTextGenerator::class, $fake);

    return $fake;
}

beforeEach(function () {
    Cache::flush();
    Storage::fake('local');

    $this->manager = User::factory()->manager()->create();
    $this->otherManager = User::factory()->manager()->create();
    $this->admin = User::factory()->admin()->create();
    $this->resident = User::factory()->create();
    $this->neighbour = User::factory()->create(['name' => 'Leila Neighbour']);

    $this->residence = Residence::factory()->create(['user_id' => $this->manager->id, 'name' => 'Les Palmiers']);
    $this->residence->members()->attach([$this->resident->id, $this->neighbour->id]);
    $this->foreign = Residence::factory()->create(['user_id' => $this->otherManager->id, 'name' => 'Foreign Towers']);

    $report = fn (Residence $residence, User $author, string $category, string $title, int $age, string $status = 'Pending') => ResidentReport::factory()->create([
        'residence_id' => $residence->id, 'user_id' => $author->id, 'category' => $category, 'title' => $title,
        'description' => "Details about {$title}. Private note 555-0101.", 'status' => $status, 'reported_at' => now()->subDays($age),
    ]);

    $this->coolingA = $report($this->residence, $this->neighbour, 'cooling', 'Lobby cooling stopped', 30);
    $this->coolingB = $report($this->residence, $this->resident, 'cooling', 'Hall is hot, cooling broken', 10);
    $this->power = $report($this->residence, $this->neighbour, 'power', 'Power cut in corridor', 60, 'Resolved');
    $this->foreignCooling = $report($this->foreign, $this->otherManager, 'cooling', 'Foreign cooling failure', 12);
});

it('analyses a photo and description with the multimodal AI and validates the suggested category', function () {
    $ai = multimodalAi(fn () => json_encode([
        'photo_assessment' => 'relevant',
        'observations' => [['detail' => 'An air vent with no visible airflow streamers.', 'source' => 'photo'], ['detail' => 'Resident says the hall is hot.', 'source' => 'description']],
        'suggested_category' => 'cooling',
        'category_reason' => 'The photo and text are about cooling.',
        'confidence' => 'medium',
        'possible_causes' => ['The unit may have lost refrigerant'],
        'uncertainties' => ['The photo cannot show whether the unit is powered.'],
        'follow_up_questions' => ['Since when is it hot?'],
        'safety_note' => '',
        'draft' => ['title' => 'No cold air in the shared hall', 'description' => 'The shared hall vents blow no cold air since Monday.'],
    ]));

    $response = $this->actingAs($this->resident)->post(route('incident-lens.analyze'), [
        'residence_id' => $this->residence->id,
        'title' => 'Hot hall',
        'description' => 'The shared hall is too hot to stay in.',
        'photo' => fakePhoto(),
    ], ['Accept' => 'application/json']);

    $response->assertOk()
        ->assertJsonPath('source', 'ai')
        ->assertJsonPath('model', 'fake-gemini')
        ->assertJsonPath('photo_sent', true)
        ->assertJsonPath('analysis.suggested_category', 'cooling')
        ->assertJsonPath('analysis.observations.0.source', 'photo')
        ->assertJsonPath('analysis.draft.title', 'No cold air in the shared hall')
        ->assertJsonPath('analysis.follow_up_questions.0', 'Since when is it hot?')
        ->assertJsonMissingPath('similar');

    expect($ai->calls[0]['mime'])->toBe('image/png')
        ->and(strlen($ai->calls[0]['image']))->toBeGreaterThan(0)
        ->and($ai->calls[0]['prompt'])->toContain('The shared hall is too hot to stay in.')->not->toContain('Lobby cooling stopped')->not->toContain('Foreign cooling failure')->not->toContain('Leila')
        ->and($response->getContent())->not->toContain('555-0101')->not->toContain('Leila');
});

it('rejects an invented category and says honestly when the AI cannot analyse', function () {
    multimodalAi(fn () => json_encode(['observations' => [], 'suggested_category' => 'plumbing-ish', 'draft' => ['title' => 'x', 'description' => 'y']]));

    $this->actingAs($this->resident)->postJson(route('incident-lens.analyze'), ['residence_id' => $this->residence->id, 'description' => 'Something is wrong in the hall.'])
        ->assertOk()->assertJsonPath('analysis.suggested_category', null);

    multimodalAi(null);

    $this->actingAs($this->resident)->postJson(route('incident-lens.analyze'), ['residence_id' => $this->residence->id, 'description' => 'Something is wrong in the hall.'])
        ->assertOk()->assertJsonPath('source', 'unavailable')->assertJsonMissingPath('analysis')
        ->assertJsonPath('notice', fn (string $notice) => str_contains($notice, 'No AI analysis was produced'));

    // A text-only provider cannot read photos: the Lens says so instead of ignoring the photo.
    app()->instance(AiTextGenerator::class, new class implements AiTextGenerator
    {
        public function isAvailable(): bool
        {
            return true;
        }

        public function generate(string $system, string $prompt, int $maxTokens = 1200): ?string
        {
            return '{}';
        }
    });

    $this->actingAs($this->resident)->post(route('incident-lens.analyze'), [
        'residence_id' => $this->residence->id, 'description' => 'Something is wrong in the hall.', 'photo' => fakePhoto('a.png'),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('notice', fn (string $notice) => str_contains($notice, 'needs Gemini to read photos'));
});

it('validates lens uploads and access', function () {
    multimodalAi(fn () => '{}');
    $valid = ['residence_id' => $this->residence->id, 'description' => 'The shared hall is too hot to stay in.'];

    $this->actingAs($this->resident)->post(route('incident-lens.analyze'), $valid + ['photo' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertJsonValidationErrors(['photo' => 'The photo must be a JPG, PNG or WebP image.']);
    $this->actingAs($this->resident)->post(route('incident-lens.analyze'), $valid + ['photo' => fakePhoto('big.png')->size(6000)], ['Accept' => 'application/json'])
        ->assertJsonValidationErrors(['photo' => 'The photo must be smaller than 5 MB.']);
    $this->actingAs($this->resident)->postJson(route('incident-lens.analyze'), ['residence_id' => $this->residence->id])
        ->assertJsonValidationErrors(['description' => 'Describe the problem or add a photo before asking the AI Lens.']);

    $this->actingAs($this->resident)->postJson(route('incident-lens.analyze'), ['residence_id' => $this->foreign->id] + $valid)->assertNotFound();
    $this->actingAs($this->manager)->postJson(route('incident-lens.analyze'), $valid)->assertForbidden();
});

it('requires the resident to confirm an AI-assisted report and stores its photo privately', function () {
    $payload = [
        'residence_id' => $this->residence->id, 'title' => 'No cold air in the shared hall', 'category' => 'cooling',
        'description' => 'The shared hall vents blow no cold air.', 'reported_at' => today()->toDateString(),
        'photo' => fakePhoto(), 'ai_assisted' => 1,
    ];

    $this->actingAs($this->resident)->post(route('resident-reports.store'), $payload)
        ->assertSessionHasErrors(['review_confirmed' => 'Please confirm that you reviewed the AI-assisted report before submitting it.']);

    $this->actingAs($this->resident)->post(route('resident-reports.store'), ['review_confirmed' => 1] + $payload)
        ->assertRedirect(route('resident-reports.index'));

    $report = ResidentReport::where('title', 'No cold air in the shared hall')->sole();
    expect($report->ai_assisted)->toBeTrue()->and($report->photo_path)->toStartWith('report-photos/');
    Storage::disk('local')->assertExists($report->photo_path);

    $this->actingAs($this->resident)->get(route('report-photos.show', $report))->assertOk();
    $this->actingAs($this->manager)->get(route('report-photos.show', $report))->assertOk();
    $this->actingAs($this->admin)->get(route('report-photos.show', $report))->assertOk();
    $this->actingAs($this->neighbour)->get(route('report-photos.show', $report))->assertForbidden();
    $this->actingAs($this->otherManager)->get(route('report-photos.show', $report))->assertForbidden();

    $this->actingAs($this->resident)->get(route('resident-reports.show', $report))->assertSee('AI Incident Lens')->assertSee(route('report-photos.show', $report));
});

it('keeps residents out of the constellation', function () {
    $this->actingAs($this->resident)->get(route('constellation.index'))->assertForbidden();
    $this->actingAs($this->resident)->get(route('command-center.index', ['tab' => 'map']))->assertRedirect(route('resident-reports.index'));
    $this->actingAs($this->resident)->getJson(route('constellation.graph'))->assertForbidden();
    $this->actingAs($this->resident)->postJson(route('constellation.investigate'), ['report_ids' => [$this->coolingA->id, $this->coolingB->id]])->assertForbidden();
});

it('requires authentication for the constellation and the lens', function () {
    $this->getJson(route('constellation.graph'))->assertUnauthorized();
    $this->postJson(route('incident-lens.analyze'), [])->assertUnauthorized();
});

it('builds the manager graph from their residences with semantic and pattern links', function () {
    $ai = multimodalAi();

    // The old page now lands on the "Incident map" tab of the Command Center, which explains how to read the map.
    $this->actingAs($this->manager)->get(route('constellation.index', ['focus' => $this->coolingA->id]))
        ->assertRedirect(route('command-center.index', ['tab' => 'map', 'focus' => $this->coolingA->id]));

    $this->actingAs($this->manager)->get(route('command-center.index', ['tab' => 'map']))->assertOk()
        ->assertSee('AI Incident Constellation')
        ->assertSee('How to read this map')
        ->assertSee('A dot is one report.')
        ->assertSee('data-focus=""', false);

    $graph = $this->actingAs($this->manager)->getJson(route('constellation.graph', ['days' => 180]))->assertOk();

    $ids = collect($graph->json('nodes'))->pluck('id');
    expect($ids->sort()->values()->all())->toBe(collect([$this->coolingA->id, $this->coolingB->id, $this->power->id])->sort()->values()->all())
        ->and($graph->json('semantic.available'))->toBeTrue()
        ->and($graph->json('semantic.model'))->toBe('fake-embedding');

    $edges = collect($graph->json('edges'));
    $cooling = $edges->first(fn ($edge) => collect([$edge['source'], $edge['target']])->sort()->values()->all() === collect([$this->coolingA->id, $this->coolingB->id])->sort()->values()->all());

    expect($edges)->toHaveCount(1)
        ->and($cooling)->toMatchArray(['semantic' => true, 'pattern' => true, 'gap_days' => 20])
        ->and($cooling['similarity'])->toBeGreaterThan(0.9)
        ->and($graph->getContent())->not->toContain('Foreign');

    // Vectors are cached on the reports: a second load does not call the embedding API again.
    $this->actingAs($this->manager)->getJson(route('constellation.graph'))->assertOk();
    expect($ai->embedCalls)->toBe(1);

    $this->actingAs($this->manager)->getJson(route('constellation.graph', ['residence_id' => $this->foreign->id]))->assertJsonValidationErrors('residence_id');
});

it('falls back to labelled pattern and keyword links when embeddings are unavailable', function () {
    multimodalAi(null);
    app()->instance(AiTextGenerator::class, new class implements AiTextGenerator
    {
        public function isAvailable(): bool
        {
            return false;
        }

        public function generate(string $system, string $prompt, int $maxTokens = 1200): ?string
        {
            return null;
        }
    });

    $graph = $this->actingAs($this->manager)->getJson(route('constellation.graph'))->assertOk()
        ->assertJsonPath('semantic.available', false)
        ->assertJsonPath('semantic.notice', fn (string $notice) => str_contains($notice, 'Only rule-based pattern links'));

    $edges = collect($graph->json('edges'));

    $cooling = $edges->first(fn ($edge) => collect([$edge['source'], $edge['target']])->sort()->values()->all() === collect([$this->coolingA->id, $this->coolingB->id])->sort()->values()->all());

    expect($edges->every(fn ($edge) => $edge['semantic'] === false && $edge['similarity'] === null))->toBeTrue()
        ->and($cooling)->toMatchArray([
            'pattern' => true, 'keyword' => true, 'keyword_shared' => 'both about cooling / air conditioning',
        ]);
});

it('lets admins look for cross-residence patterns', function () {
    multimodalAi();

    $within = collect($this->actingAs($this->admin)->getJson(route('constellation.graph'))->json('edges'));
    $across = collect($this->actingAs($this->admin)->getJson(route('constellation.graph', ['cross' => 1]))->json('edges'));

    $touchesForeign = fn ($edge) => in_array($this->foreignCooling->id, [$edge['source'], $edge['target']], true);
    expect($within->contains($touchesForeign))->toBeFalse()
        ->and($across->contains($touchesForeign))->toBeTrue();
});

it('investigates only authorized reports and validates every id the AI returns', function () {
    $case = ResponseCase::factory()->for($this->residence)->create(['title' => 'Lobby cooling response', 'created_by' => $this->manager->id]);
    $case->reports()->attach($this->coolingA->id);
    $ai = multimodalAi(fn () => json_encode([
        'summary' => 'Two reports describe the same cooling failure.',
        'relationships' => [
            ['report_ids' => [$this->coolingA->id, $this->coolingB->id], 'relation' => 'same recurring problem', 'evidence' => ['Both mention cooling.'], 'confidence' => 'high'],
            ['report_ids' => [$this->coolingA->id, 999999], 'relation' => 'possible duplicate', 'evidence' => ['invented'], 'confidence' => 'high'],
        ],
        'hypotheses' => [['statement' => 'The lobby unit may be failing again.', 'supporting_report_ids' => [$this->coolingA->id, $this->foreignCooling->id], 'evidence' => ['Reported twice.'], 'confidence' => 'certain']],
        'uncertainties' => ['No technician report.'],
        'next_steps' => ['Ask the contractor to inspect the unit.'],
    ]));

    $this->actingAs($this->manager)->postJson(route('constellation.investigate'), ['report_ids' => [$this->coolingA->id, $this->foreignCooling->id]])
        ->assertJsonValidationErrors(['report_ids' => 'Some selected reports do not exist or are outside your residences.']);
    expect($ai->calls)->toBe([]);

    $result = $this->actingAs($this->manager)->postJson(route('constellation.investigate'), ['report_ids' => [$this->coolingA->id, $this->coolingB->id]])
        ->assertOk()
        ->assertJsonPath('source', 'ai')
        ->assertJsonCount(1, 'relationships')
        ->assertJsonPath('hypotheses.0.supporting_report_ids', [$this->coolingA->id])
        ->assertJsonPath('hypotheses.0.confidence', 'low')
        ->assertJsonCount(1, 'cases')
        ->assertJsonPath('cases.0.title', 'Lobby cooling response')
        ->assertJsonPath('cases.0.stage', 'Reported')
        ->assertJsonPath('cases.0.report_ids', [$this->coolingA->id])
        ->assertJsonMissingPath('preparedness');

    expect($result->json('notice'))->toContain('not confirmed causes')
        ->and($ai->calls[0]['prompt'])->toContain('Lobby cooling stopped')->not->toContain('Foreign');
});

it('reports an unavailable investigation without inventing one', function () {
    multimodalAi(null);

    $this->actingAs($this->manager)->postJson(route('constellation.investigate'), ['report_ids' => [$this->coolingA->id, $this->coolingB->id]])
        ->assertOk()
        ->assertJsonPath('source', 'unavailable')
        ->assertJsonPath('summary', null)
        ->assertJsonPath('hypotheses', [])
        ->assertJsonPath('notice', fn (string $notice) => str_contains($notice, 'No AI investigation was produced'));
});
