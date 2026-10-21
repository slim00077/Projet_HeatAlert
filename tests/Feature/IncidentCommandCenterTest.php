<?php

use App\Contracts\AiTextGenerator;
use App\Enums\ResponseStage;
use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\ResponseCase;
use App\Models\ResponseTask;
use App\Models\ResponseUpdate;
use App\Models\User;
use App\Notifications\ResponseCaseUpdated;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    // No AI backend: the Constellation falls back to rule-based pattern links and nothing leaves the app.
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

    $this->manager = User::factory()->manager()->create();
    $this->otherManager = User::factory()->manager()->create();
    $this->admin = User::factory()->admin()->create();
    $this->resident = User::factory()->create(['name' => 'Amine']);
    $this->neighbour = User::factory()->create(['name' => 'Leila']);

    $this->residence = Residence::factory()->create(['user_id' => $this->manager->id]);
    $this->residence->members()->attach([$this->resident->id, $this->neighbour->id]);
    $this->foreignResidence = Residence::factory()->create(['user_id' => $this->otherManager->id]);

    $this->first = ResidentReport::factory()->create([
        'residence_id' => $this->residence->id, 'user_id' => $this->resident->id, 'title' => 'Lobby AC blowing warm air',
        'category' => 'cooling', 'status' => 'Pending', 'reported_at' => now()->subDays(6),
    ]);
    $this->second = ResidentReport::factory()->create([
        'residence_id' => $this->residence->id, 'user_id' => $this->neighbour->id, 'title' => 'Corridor too hot since Monday',
        'category' => 'cooling', 'status' => 'In Progress', 'reported_at' => now()->subDays(3),
    ]);
});

function openCase(array $overrides = []): TestResponse
{
    return test()->actingAs(test()->manager)->post(route('residences.response-cases.store', test()->residence), $overrides + [
        'title' => 'Cooling failure in the lobby',
        'priority' => 'high',
        'target_date' => now()->addWeek()->toDateString(),
        'report_ids' => [test()->first->id, test()->second->id],
    ]);
}

it('lets the manager group related reports into one case without changing the reports', function () {
    Notification::fake();
    $before = $this->first->fresh()->only(['status', 'title', 'description', 'updated_at']);

    openCase()->assertRedirect(route('response-cases.show', ResponseCase::sole()));

    $case = ResponseCase::sole();

    expect($case)
        ->stage->toBe(ResponseStage::Reported)
        ->created_by->toBe($this->manager->id)
        ->and($case->reports()->pluck('resident_reports.id')->sort()->values()->all())->toBe([$this->first->id, $this->second->id])
        ->and($this->first->fresh()->only(['status', 'title', 'description', 'updated_at']))->toEqual($before)
        ->and($case->updates()->sole())->kind->toBe(ResponseUpdate::KIND_CREATED)->is_public->toBeTrue();

    Notification::assertSentTo([$this->resident, $this->neighbour], ResponseCaseUpdated::class);
    Notification::assertNotSentTo($this->manager, ResponseCaseUpdated::class);
});

it('refuses reports from another residence or already handled by an open case', function () {
    $foreign = ResidentReport::factory()->create(['residence_id' => $this->foreignResidence->id, 'status' => 'Pending']);

    openCase(['report_ids' => [$this->first->id, $foreign->id]])
        ->assertSessionHasErrors(['report_ids' => 'Some selected reports do not exist or do not belong to '.$this->residence->name.'.']);

    openCase(['report_ids' => [$this->first->id]]);

    openCase(['report_ids' => [$this->first->id, $this->second->id]])
        ->assertSessionHasErrors(['report_ids' => "Report(s) #{$this->first->id} are already handled by another open response case."]);

    expect(ResponseCase::count())->toBe(1);
});

it('keeps response rooms to the owning manager and admins', function () {
    openCase();
    $case = ResponseCase::sole();

    $this->actingAs($this->otherManager)->get(route('response-cases.show', $case))->assertForbidden();
    $this->actingAs($this->resident)->get(route('response-cases.show', $case))->assertForbidden();
    $this->actingAs($this->otherManager)->patch(route('response-cases.stage', $case), ['stage' => 'action'])->assertForbidden();
    $this->actingAs($this->otherManager)->post(route('response-cases.tasks.store', $case), ['title' => 'Sabotage'])->assertForbidden();
    $this->actingAs($this->otherManager)->post(route('residences.response-cases.store', $this->residence), ['title' => 'x', 'priority' => 'low', 'report_ids' => [$this->first->id]])->assertForbidden();
    $this->actingAs($this->resident)->get(route('residences.response-cases.create', $this->residence))->assertForbidden();

    $this->actingAs($this->manager)->get(route('response-cases.show', $case))->assertOk()
        ->assertSee('Cooling failure in the lobby')
        ->assertSee('Lobby AC blowing warm air')
        ->assertSee('Response workflow');
    $this->actingAs($this->admin)->get(route('response-cases.show', $case))->assertOk();
});

it('moves a case through the workflow, logging and notifying each stage change', function () {
    openCase();
    $case = ResponseCase::sole();
    Notification::fake();

    $this->actingAs($this->manager)->patch(route('response-cases.stage', $case), ['stage' => 'investigating', 'note' => 'Technician visit booked.'])
        ->assertSessionHas('success');

    expect($case->fresh()->stage)->toBe(ResponseStage::Investigating)
        ->and($case->updates()->where('kind', ResponseUpdate::KIND_STAGE)->sole())
        ->is_public->toBeTrue()
        ->body->toBe('Status moved from Reported to Under Investigation. Technician visit booked.');

    Notification::assertSentTo($this->resident, ResponseCaseUpdated::class, fn (ResponseCaseUpdated $notification) => $notification->toArray($this->resident)['stage_label'] === 'Under Investigation'
        && $notification->toArray($this->resident)['resident_report_id'] === $this->first->id);

    $this->actingAs($this->manager)->patch(route('response-cases.stage', $case), ['stage' => 'closed'])->assertSessionHasErrors('stage');
});

it('only resolves a case once every task is done', function () {
    openCase();
    $case = ResponseCase::sole();
    $task = ResponseTask::factory()->for($case)->create();

    $this->actingAs($this->manager)->patch(route('response-cases.stage', $case), ['stage' => 'resolved'])
        ->assertSessionHasErrors(['stage' => 'Complete or remove the open tasks before resolving this case.']);

    $this->actingAs($this->manager)->patch(route('response-tasks.update', $task), ['progress' => 100])->assertSessionHas('success');
    $this->actingAs($this->manager)->patch(route('response-cases.stage', $case), ['stage' => 'resolved'])->assertSessionHas('success');

    expect($task->fresh())->status->toBe(ResponseTask::STATUS_DONE)->completed_at->not->toBeNull()
        ->and($case->fresh())->stage->toBe(ResponseStage::Resolved)->resolved_at->not->toBeNull()
        ->and($this->first->fresh()->status)->toBe('Pending');
});

it('plans tasks for staff or external crews with deadlines and progress', function () {
    openCase();
    $case = ResponseCase::sole();

    $this->actingAs($this->manager)->post(route('response-cases.tasks.store', $case), ['title' => 'Inspect the unit', 'assigned_to' => $this->resident->id])
        ->assertSessionHasErrors(['assigned_to' => 'Tasks can only be assigned to the residence manager or an administrator.']);

    $this->actingAs($this->manager)->post(route('response-cases.tasks.store', $case), [
        'title' => 'Replace the compressor', 'assignee_label' => 'CoolFix technicians', 'due_date' => now()->subDay()->toDateString(),
    ])->assertSessionHas('success');

    $task = ResponseTask::sole();
    expect($task)->assigneeName()->toBe('CoolFix technicians')->isOverdue()->toBeTrue()->created_by->toBe($this->manager->id);

    $this->actingAs($this->manager)->patch(route('response-tasks.update', $task), ['progress' => 40]);
    expect($task->fresh())->status->toBe(ResponseTask::STATUS_IN_PROGRESS)->progress->toBe(40);

    $this->actingAs($this->manager)->patch(route('response-tasks.update', $task), ['progress' => 140])->assertSessionHasErrors('progress');
});

it('shows residents only published updates, after a manager approves internal notes', function () {
    openCase();
    $case = ResponseCase::sole();
    Notification::fake();

    $this->actingAs($this->manager)->post(route('response-cases.updates.store', $case), ['body' => 'Supplier quote is too expensive, checking another.']);
    $note = ResponseUpdate::where('kind', ResponseUpdate::KIND_NOTE)->sole();

    expect($note->is_public)->toBeFalse();
    Notification::assertNothingSent();
    $this->actingAs($this->resident)->get(route('resident-reports.show', $this->first))->assertOk()
        ->assertSee('Cooling failure in the lobby')
        ->assertDontSee('Supplier quote is too expensive');

    $this->actingAs($this->otherManager)->patch(route('response-updates.publish', $note))->assertForbidden();
    $this->actingAs($this->manager)->patch(route('response-updates.publish', $note))->assertSessionHas('success');

    $this->actingAs($this->resident)->get(route('resident-reports.show', $this->first))->assertSee('Supplier quote is too expensive');
    Notification::assertSentTo([$this->resident, $this->neighbour], ResponseCaseUpdated::class);
});

it('lets residents confirm a resolution of their own report only once work awaits verification', function () {
    openCase();
    $case = ResponseCase::sole();
    $payload = ['is_resolved' => 1, 'comment' => 'Cool again this morning.'];

    $this->actingAs($this->resident)->post(route('resident-reports.confirmation', $this->first), $payload)->assertForbidden();

    $case->update(['stage' => ResponseStage::Verification]);

    $this->actingAs($this->neighbour)->post(route('resident-reports.confirmation', $this->first), $payload)->assertForbidden();
    $this->actingAs($this->admin)->post(route('resident-reports.confirmation', $this->first), $payload)->assertForbidden();
    $this->actingAs($this->resident)->post(route('resident-reports.confirmation', $this->first), $payload)->assertSessionHas('success');
    $this->actingAs($this->resident)->post(route('resident-reports.confirmation', $this->first), ['is_resolved' => 0]);

    expect($case->confirmations()->sole())->is_resolved->toBeFalse()->user_id->toBe($this->resident->id)
        ->and($case->updates()->where('kind', ResponseUpdate::KIND_CONFIRMATION)->count())->toBe(2);

    $this->actingAs($this->manager)->get(route('response-cases.show', $case))->assertSee('Resident says it still appears');
});

it('shows residents the tracking of their own cases inside My Reports and clears their notifications', function () {
    openCase(['report_ids' => [$this->first->id]]);
    $foreignCase = ResponseCase::factory()->for($this->foreignResidence)->create(['title' => 'Someone else problem']);

    $this->actingAs($this->resident)->get(route('resident-reports.index'))->assertOk()
        ->assertSee('Live tracking of your reports')
        ->assertSee('Lobby AC blowing warm air')
        ->assertSee('Response case')
        ->assertSee('New since your last visit')
        ->assertDontSee($foreignCase->title);

    expect($this->resident->unreadNotifications()->count())->toBe(0);

    $this->actingAs($this->neighbour)->get(route('resident-reports.index'))->assertOk()
        ->assertDontSee('Lobby AC blowing warm air')
        ->assertSee('Corridor too hot since Monday');

    // The separate tracker page is gone: residents are sent to My Reports.
    $this->actingAs($this->resident)->get(route('command-center.index'))->assertRedirect(route('resident-reports.index'));
});

it('shows staff the workflow board and admins every residence with overdue tasks', function () {
    openCase();
    $foreignCase = ResponseCase::factory()->for($this->foreignResidence)->create(['title' => 'Water pressure in block C']);
    ResponseTask::factory()->for($foreignCase)->overdue()->create(['title' => 'Call the water utility']);

    $this->actingAs($this->manager)->get(route('command-center.index'))->assertOk()
        ->assertSee('Incident Command Center')
        ->assertSee('Cooling failure in the lobby')
        ->assertDontSee('Water pressure in block C');

    $this->actingAs($this->admin)->get(route('command-center.index'))->assertOk()
        ->assertSee('Cooling failure in the lobby')
        ->assertSee('Water pressure in block C')
        ->assertSee('Call the water utility')
        ->assertSee('Response performance');
});

it('suggests related reports from the constellation without linking them until the manager confirms', function () {
    openCase(['report_ids' => [$this->first->id]]);
    $case = ResponseCase::sole();
    $unrelated = ResidentReport::factory()->create([
        'residence_id' => $this->residence->id, 'user_id' => $this->resident->id, 'category' => 'water', 'status' => 'Pending', 'reported_at' => now()->subDays(2),
        'title' => 'Dripping tap in the courtyard', 'description' => 'The courtyard tap drips all day.',
    ]);

    $this->actingAs($this->otherManager)->getJson(route('response-cases.suggestions', $case))->assertForbidden();

    $this->actingAs($this->manager)->getJson(route('response-cases.suggestions', $case))->assertOk()
        ->assertJsonCount(1, 'suggestions')
        ->assertJsonPath('suggestions.0.id', $this->second->id)
        ->assertJsonPath('suggestions.0.evidence', 'keyword match (both about cooling / air conditioning) + same category, 3 days apart');

    expect($case->reports()->count())->toBe(1);

    $this->actingAs($this->manager)->post(route('response-cases.reports.store', $case), ['report_ids' => [$this->second->id], 'source' => 'constellation'])
        ->assertSessionHas('success');

    expect($case->reports()->whereKey($this->second->id)->sole()->pivot->source)->toBe('constellation')
        ->and($case->reports()->whereKey($unrelated->id)->exists())->toBeFalse();
});

it('pre-selects a constellation cluster on the case form, leaving out reports outside the residence', function () {
    $foreign = ResidentReport::factory()->create(['residence_id' => $this->foreignResidence->id, 'title' => 'Foreign elevator issue', 'status' => 'Pending']);

    $this->actingAs($this->manager)
        ->get(route('residences.response-cases.create', [$this->residence, 'report_ids' => [$this->first->id, $foreign->id], 'source' => 'constellation']))
        ->assertOk()
        ->assertViewHas('selected', [$this->first->id])
        ->assertViewHas('source', 'constellation')
        ->assertSee('Corridor too hot since Monday')
        ->assertDontSee('Foreign elevator issue');
});
