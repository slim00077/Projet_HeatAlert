<?php

use App\Models\Advice;
use App\Models\AdviceCategory;
use App\Models\AdviceModerationEvent;
use App\Models\AdviceQualityReport;
use App\Models\User;
use App\Notifications\AdviceModerationUpdated;
use App\Services\GeminiTextGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeAdviceQualityAnalysis(array $analysis): void
{
    config([
        'services.gemini.key' => 'test-gemini-key',
        'services.gemini.model' => 'gemini-2.5-flash',
    ]);
    app()->forgetInstance(GeminiTextGenerator::class);
    Http::preventStrayRequests();
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode($analysis)]]]]],
        ]),
    ]);
}

it('analyzes submitted advice with Gemini and records source uncertainty and an audit event', function () {
    $category = AdviceCategory::factory()->create(['name' => 'Hydration']);
    $existingAdvice = Advice::factory()->for($category, 'category')->create([
        'title' => 'Drink water throughout the day',
        'content' => 'Drink water regularly during hot weather.',
    ]);
    fakeAdviceQualityAnalysis([
        'score' => 78,
        'summary' => 'Potential overlap with an existing hydration recommendation.',
        'findings' => [[
            'type' => 'semantic_duplicate',
            'severity' => 'medium',
            'explanation' => 'This advice substantially overlaps an existing hydration entry.',
            'related_advice_ids' => [$existingAdvice->id, 999999],
        ]],
    ]);
    $resident = User::factory()->create();

    $this->actingAs($resident)->post(route('advice.store'), [
        'advice_category_id' => $category->id,
        'risk_level' => 'high',
        'title' => 'Stay hydrated',
        'content' => 'Drink small amounts of water often.',
        'source_url' => 'https://health.example.org/heat',
        'is_approved' => true,
    ])->assertRedirect(route('advice.index'));

    $advice = Advice::where('title', 'Stay hydrated')->sole();
    $report = AdviceQualityReport::where('advice_id', $advice->id)->sole();

    expect($advice->is_approved)->toBeFalse()
        ->and($advice->moderation_status)->toBe('pending')
        ->and($advice->source_url)->toBe('https://health.example.org/heat')
        ->and($report->score)->toBe(78)
        ->and($report->analysis_status)->toBe('completed')
        ->and($report->analyzed_model)->toBe('gemini-2.5-flash')
        ->and($report->source_status)->toBe('provided_unverified')
        ->and($report->advice_snapshot['title'])->toBe('Stay hydrated')
        ->and($report->advice_snapshot['category'])->toBe('Hydration')
        ->and(collect($report->findings)->pluck('type'))->toContain('semantic_duplicate', 'source_unverified')
        ->and(collect($report->findings)->firstWhere('type', 'semantic_duplicate')['related_advice_ids'])->toBe([$existingAdvice->id])
        ->and(AdviceModerationEvent::where('advice_id', $advice->id)->value('action'))->toBe('analysis_completed');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'models/gemini-2.5-flash:generateContent')
        && $request->hasHeader('x-goog-api-key', 'test-gemini-key')
        && str_contains($request['contents'][0]['parts'][0]['text'], $existingAdvice->title)
        && str_contains($request['contents'][0]['parts'][0]['text'], 'Hydration'));
});

it('keeps administrator-created advice pending until the human approves it', function () {
    fakeAdviceQualityAnalysis([
        'score' => 91,
        'summary' => 'Clear, concise and consistent with the comparison entries.',
        'findings' => [],
    ]);
    $admin = User::factory()->admin()->create();
    $category = AdviceCategory::factory()->create(['name' => 'Cooling']);

    $this->actingAs($admin)->post(route('admin.advices.store'), [
        'advice_category_id' => $category->id,
        'risk_level' => 'medium',
        'title' => 'Keep indoor spaces cooler',
        'content' => 'Close curtains on sun-facing windows during the hottest hours.',
        'source_url' => 'https://health.example.org/cooling',
    ])->assertRedirect();

    $advice = Advice::where('title', 'Keep indoor spaces cooler')->sole();
    $report = AdviceQualityReport::where('advice_id', $advice->id)->sole();

    expect($advice->is_approved)->toBeFalse()
        ->and($advice->moderation_status)->toBe('pending')
        ->and($report->analysis_status)->toBe('completed')
        ->and($report->analyzed_by)->toBe($admin->id)
        ->and(AdviceModerationEvent::where('advice_id', $advice->id)->value('action'))->toBe('analysis_completed');
});

it('records missing sources and a transparent unavailable report when Gemini is not configured', function () {
    config(['services.gemini.key' => null]);
    app()->forgetInstance(GeminiTextGenerator::class);
    Http::preventStrayRequests();
    $category = AdviceCategory::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('advice.store'), [
        'advice_category_id' => $category->id,
        'risk_level' => 'medium',
        'title' => 'Keep rooms cool',
        'content' => 'Close blinds during the hottest hours.',
    ])->assertRedirect(route('advice.index'));

    $advice = Advice::where('title', 'Keep rooms cool')->sole();
    $report = AdviceQualityReport::where('advice_id', $advice->id)->sole();

    expect($advice->moderation_status)->toBe('pending')
        ->and($report->analysis_status)->toBe('unavailable')
        ->and($report->score)->toBeNull()
        ->and($report->source_status)->toBe('missing')
        ->and(collect($report->findings)->pluck('type'))->toContain('source_missing')
        ->and(AdviceModerationEvent::where('advice_id', $advice->id)->value('action'))->toBe('analysis_unavailable');

    Http::assertNothingSent();
});

it('does not trust malformed Gemini output and preserves a failed analysis record', function () {
    config([
        'services.gemini.key' => 'test-gemini-key',
        'services.gemini.model' => 'gemini-2.5-flash',
    ]);
    app()->forgetInstance(GeminiTextGenerator::class);
    Http::preventStrayRequests();
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '{"score": 100, "findings": "not-an-array"}']]]]],
        ]),
    ]);
    $category = AdviceCategory::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('advice.store'), [
        'advice_category_id' => $category->id,
        'risk_level' => 'low',
        'title' => 'Open windows at night',
        'content' => 'Ventilate when outdoor air is cooler.',
    ])->assertRedirect(route('advice.index'));

    $advice = Advice::where('title', 'Open windows at night')->sole();
    $report = AdviceQualityReport::where('advice_id', $advice->id)->sole();

    expect($report->analysis_status)->toBe('failed')
        ->and($report->score)->toBeNull()
        ->and($report->summary)->toContain('could not be completed')
        ->and($advice->is_approved)->toBeFalse();
});

it('records Gemini provider errors without fabricating a quality score', function () {
    config([
        'services.gemini.key' => 'test-gemini-key',
        'services.gemini.model' => 'gemini-2.5-flash',
    ]);
    app()->forgetInstance(GeminiTextGenerator::class);
    Http::preventStrayRequests();
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'Temporary provider outage']], 503),
    ]);
    $category = AdviceCategory::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('advice.store'), [
        'advice_category_id' => $category->id,
        'risk_level' => 'medium',
        'title' => 'Take breaks in a cool place',
        'content' => 'Rest in a cool place during hot weather.',
    ])->assertRedirect(route('advice.index'));

    $advice = Advice::where('title', 'Take breaks in a cool place')->sole();
    $report = AdviceQualityReport::where('advice_id', $advice->id)->sole();

    expect($report->analysis_status)->toBe('failed')
        ->and($report->score)->toBeNull()
        ->and($report->findings[0]['type'])->toBe('source_missing')
        ->and(AdviceModerationEvent::where('advice_id', $advice->id)->value('action'))->toBe('analysis_failed');
});

it('rejects non-http source URLs before storing or sending submitted content to Gemini', function () {
    Http::preventStrayRequests();
    $category = AdviceCategory::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('advice.store'), [
        'advice_category_id' => $category->id,
        'risk_level' => 'low',
        'title' => 'Unsafe source',
        'content' => 'Untrusted advice content.',
        'source_url' => 'javascript:alert(1)',
    ])->assertSessionHasErrors('source_url');

    expect(Advice::count())->toBe(0);
    Http::assertNothingSent();
});

it('requires administrators to provide a reason when rejecting or requesting changes', function () {
    $admin = User::factory()->admin()->create();
    $advice = Advice::factory()->create([
        'is_approved' => false,
        'moderation_status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.advices.reject', $advice), [])
        ->assertSessionHasErrors('note');
    $this->post(route('admin.advices.request-changes', $advice), [
        'note' => 'Please add a traceable public-health source.',
    ])->assertRedirect(route('admin.advices.show', $advice));

    expect($advice->fresh()->moderation_status)->toBe('changes_requested')
        ->and(AdviceModerationEvent::where('advice_id', $advice->id)->value('action'))->toBe('changes_requested');

    $this->post(route('admin.advices.reject', $advice), [
        'note' => 'The advice conflicts with the current approved guidance.',
    ])->assertRedirect(route('admin.advices.show', $advice));

    expect($advice->fresh()->moderation_status)->toBe('rejected')
        ->and($advice->fresh()->is_approved)->toBeFalse()
        ->and(AdviceModerationEvent::where('advice_id', $advice->id)->count())->toBe(2);
});

it('notifies the submitter of a rejection and shows the private reason in their submission page', function () {
    $submitter = User::factory()->create();
    $otherUser = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $category = AdviceCategory::factory()->create();
    $advice = Advice::factory()->for($category, 'category')->create([
        'submitted_by' => $submitter->id,
        'is_approved' => false,
        'moderation_status' => 'pending',
    ]);
    Advice::factory()->for($category, 'category')->create([
        'submitted_by' => $otherUser->id,
        'title' => 'Another user private submission',
        'is_approved' => false,
        'moderation_status' => 'pending',
    ]);
    $reason = 'Please provide a reliable source and clarify who this advice applies to.';

    $this->actingAs($admin)
        ->post(route('admin.advices.reject', $advice), ['note' => $reason])
        ->assertRedirect(route('admin.advices.show', $advice));

    $notification = $submitter->notifications()->sole();
    expect($notification->type)->toBe(AdviceModerationUpdated::class)
        ->and($notification->data['status'])->toBe('rejected')
        ->and($notification->data['note'])->toBe($reason)
        ->and($advice->fresh()->submitted_by)->toBe($submitter->id);

    $this->actingAs($submitter)->get(route('advice.submissions.index'))
        ->assertSee('My Advice Submissions')
        ->assertSee($advice->title)
        ->assertSee('This advice was rejected by an administrator.')
        ->assertSee($reason)
        ->assertDontSee('Another user private submission')
        ->assertDontSee('>1</span>', false);

    expect($submitter->notifications()->sole()->read_at)->not->toBeNull();

    $this->actingAs($otherUser)
        ->get(route('advice.submissions.index', ['advice' => $advice->id]))
        ->assertNotFound();
});

it('keeps quality reports and moderation controls private to administrators', function () {
    $category = AdviceCategory::factory()->create();
    $advice = Advice::factory()->for($category, 'category')->create([
        'is_approved' => false,
        'moderation_status' => 'pending',
    ]);
    AdviceQualityReport::create([
        'advice_id' => $advice->id,
        'analysis_status' => 'completed',
        'score' => 65,
        'summary' => 'Review source and overlap warnings.',
        'findings' => [['type' => 'source_missing', 'severity' => 'high', 'explanation' => 'No source URL provided.']],
        'source_status' => 'missing',
        'advice_snapshot' => [
            'category' => $category->name,
            'risk_level' => $advice->risk_level->value,
            'title' => $advice->title,
            'summary' => $advice->summary,
            'content' => $advice->content,
            'source_url' => null,
        ],
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('admin.advices.show', $advice))
        ->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.advices.show', $advice))
        ->assertSee('Editorial quality score')
        ->assertSee('Not medical verification')
        ->assertSee('No source URL was provided.');
});

it('publishes advice only after explicit administrator approval and audits the decision', function () {
    $admin = User::factory()->admin()->create();
    $advice = Advice::factory()->create([
        'is_approved' => false,
        'moderation_status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.advices.approve', $advice))
        ->assertRedirect(route('admin.advices.show', $advice));

    expect($advice->fresh()->is_approved)->toBeTrue()
        ->and($advice->fresh()->moderation_status)->toBe('approved')
        ->and(AdviceModerationEvent::where('advice_id', $advice->id)->value('action'))->toBe('approved');
});

it('moves changed published advice back to review and runs a new analysis', function () {
    fakeAdviceQualityAnalysis([
        'score' => 82,
        'summary' => 'No major editorial concerns identified.',
        'findings' => [],
    ]);
    $admin = User::factory()->admin()->create();
    $category = AdviceCategory::factory()->create();
    $advice = Advice::factory()->for($category, 'category')->create([
        'is_approved' => true,
        'moderation_status' => 'approved',
    ]);

    $this->actingAs($admin)->put(route('admin.advices.update', $advice), [
        'advice_category_id' => $category->id,
        'risk_level' => 'high',
        'title' => $advice->title,
        'summary' => $advice->summary,
        'content' => 'Updated heat-safety guidance requiring another review.',
        'source_url' => 'https://health.example.org/advice',
    ])->assertRedirect(route('admin.advices.show', $advice));

    expect($advice->fresh()->is_approved)->toBeFalse()
        ->and($advice->fresh()->moderation_status)->toBe('pending')
        ->and(AdviceQualityReport::where('advice_id', $advice->id)->count())->toBe(1)
        ->and(AdviceModerationEvent::where('advice_id', $advice->id)->value('action'))->toBe('analysis_completed');
});
