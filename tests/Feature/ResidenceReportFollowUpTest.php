<?php

use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\User;

beforeEach(function () {
    $this->manager = User::factory()->manager()->create();
    $this->otherManager = User::factory()->manager()->create();
    $this->resident = User::factory()->create(['name' => 'Amine']);
    $this->neighbour = User::factory()->create(['name' => 'Leila Neighbour']);
    $this->admin = User::factory()->admin()->create();

    $this->residence = Residence::factory()->create(['user_id' => $this->manager->id]);
    $this->residence->members()->attach([$this->resident->id, $this->neighbour->id]);
});

function residenceReport(Residence $residence, User $author, array $attributes = []): ResidentReport
{
    return ResidentReport::factory()->create(['residence_id' => $residence->id, 'user_id' => $author->id] + $attributes);
}

it('records when a report is resolved and clears it when reopened', function () {
    $report = residenceReport($this->residence, $this->resident, ['status' => 'Pending']);

    $report->update(['status' => 'Resolved']);
    expect($report->fresh()->resolved_at)->not->toBeNull();

    $report->update(['status' => 'In Progress']);
    expect($report->fresh()->resolved_at)->toBeNull();
});

it('shows a resident only their own reports of the residence, with the stage of their response case', function () {
    residenceReport($this->residence, $this->resident, ['title' => 'My lobby AC is broken']);
    residenceReport($this->residence, $this->neighbour, ['title' => 'Private neighbour issue']);

    $this->actingAs($this->resident)->get(route('residences.show', $this->residence))
        ->assertOk()
        ->assertSee('My lobby AC is broken')
        ->assertSee('Incident Command Center')
        ->assertDontSee('Private neighbour issue')
        ->assertDontSee('Leila Neighbour');
});

it('shows a manager every report of their residence with its follow-up link', function () {
    $report = residenceReport($this->residence, $this->resident, ['title' => 'Elevator stuck']);

    $this->actingAs($this->manager)->get(route('residences.show', $this->residence))
        ->assertOk()
        ->assertSee('Elevator stuck')
        ->assertSee(route('managed-reports.show', $report));
});

it('lets only the residence manager or an admin record the follow-up of a report', function () {
    $report = residenceReport($this->residence, $this->resident, ['status' => 'Pending']);
    $payload = ['status' => 'Resolved', 'resolution_notes' => 'Technician replaced the compressor.'];

    $this->actingAs($this->otherManager)->get(route('managed-reports.show', $report))->assertForbidden();
    $this->actingAs($this->otherManager)->patch(route('managed-reports.update', $report), $payload)->assertForbidden();
    $this->actingAs($this->resident)->patch(route('managed-reports.update', $report), $payload)->assertForbidden();

    $this->actingAs($this->manager)->get(route('managed-reports.show', $report))->assertOk()->assertSee('Follow-up');
    $this->actingAs($this->manager)->patch(route('managed-reports.update', $report), $payload)->assertSessionHas('success');

    expect($report->fresh())
        ->status->toBe('Resolved')
        ->resolution_notes->toBe('Technician replaced the compressor.')
        ->resolved_at->not->toBeNull();

    $this->actingAs($this->resident)->get(route('resident-reports.show', $report))->assertSee('Technician replaced the compressor.');
});

it('shows admins the Command Center summary on the dashboard instead of the retired resilience overview', function () {
    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Incident Command Center')
        ->assertSee(route('command-center.index'))
        ->assertDontSee('Residence Resilience');
});

it('no longer offers the retired intelligence center, copilot, similar-incident or preparedness routes', function () {
    foreach (['intelligence', 'copilot', 'incident-insights/similar', 'preparedness-actions/1/status'] as $uri) {
        $this->actingAs($this->manager)->get('/'.$uri)->assertNotFound();
    }

    expect(Route::has('intelligence.index'))->toBeFalse()
        ->and(Route::has('copilot.ask'))->toBeFalse()
        ->and(Route::has('incident-insights.similar'))->toBeFalse()
        ->and(Route::has('residences.preparedness-actions.create'))->toBeFalse();
});
