<?php

use App\Models\HeatAlert;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->neighborhood = Neighborhood::factory()->create(['name' => 'La Marsa', 'latitude' => 36.8782, 'longitude' => 10.3247]);
    $this->resident = User::factory()->create();
    $this->admin = User::factory()->admin()->create();
});

function sendReport($test, User $user, string $title = 'Courtyard without any shade')
{
    return $test->actingAs($user)->postJson(route('map.alerts.store'), [
        'latitude' => 36.88,
        'longitude' => 10.32,
        'title' => $title,
        'severity_level' => 'high',
        'duration_hours' => 6,
    ]);
}

it('keeps a resident report hidden until an admin approves it', function () {
    sendReport($this, $this->resident)
        ->assertCreated()
        ->assertJsonPath('message', 'Report sent for La Marsa. It will appear on the map once an admin approves it.');

    $report = HeatAlert::sole();
    expect($report->review_status)->toBe(HeatAlert::REVIEW_PENDING);

    $this->getJson(route('map.data'))->assertJsonCount(0, 'pins');
    $this->get(route('front.alerts.index'))->assertDontSee('Courtyard without any shade');
});

it('lets the admin approve a report so it shows on the map and in the public list', function () {
    sendReport($this, $this->resident);
    $report = HeatAlert::sole();

    $this->actingAs($this->admin)->post(route('admin.alerts.approve', $report))
        ->assertRedirect()
        ->assertSessionHas('success');

    $report->refresh();
    expect($report->review_status)->toBe(HeatAlert::REVIEW_APPROVED)
        ->and($report->reviewed_by)->toBe($this->admin->id)
        ->and($report->reviewed_at)->not->toBeNull();

    $this->getJson(route('map.data'))->assertJsonCount(1, 'pins')->assertJsonPath('pins.0.title', 'Courtyard without any shade');
    $this->get(route('front.alerts.index'))->assertSee('Courtyard without any shade');
});

it('lets the admin reject a report so it never appears publicly', function () {
    sendReport($this, $this->resident);
    $report = HeatAlert::sole();

    $this->actingAs($this->admin)->post(route('admin.alerts.reject', $report))->assertSessionHas('success');

    expect($report->refresh()->review_status)->toBe(HeatAlert::REVIEW_REJECTED);
    $this->getJson(route('map.data'))->assertJsonCount(0, 'pins');
    $this->get(route('front.alerts.index'))->assertDontSee('Courtyard without any shade');
    $this->actingAs($this->admin)->get(route('admin.alerts.index'))->assertDontSee('Courtyard without any shade');
});

it('does not review a report twice', function () {
    sendReport($this, $this->resident);
    $report = HeatAlert::sole();
    $this->actingAs($this->admin)->post(route('admin.alerts.reject', $report));

    $this->actingAs($this->admin)->post(route('admin.alerts.approve', $report))->assertSessionHas('error');

    expect($report->refresh()->review_status)->toBe(HeatAlert::REVIEW_REJECTED);
});

it('only lets an admin approve or reject reports', function () {
    sendReport($this, $this->resident);
    $report = HeatAlert::sole();

    $this->actingAs($this->resident)->post(route('admin.alerts.approve', $report))->assertForbidden();
    $this->actingAs(User::factory()->manager()->create())->post(route('admin.alerts.reject', $report))->assertForbidden();
    auth()->logout();
    $this->post(route('admin.alerts.approve', $report))->assertRedirect(route('login'));

    expect($report->refresh()->review_status)->toBe(HeatAlert::REVIEW_PENDING);
});

it('sends the report made from the heat alert form to the admin as well', function () {
    $this->actingAs($this->resident)->post(route('front.alerts.store'), [
        'neighborhood_id' => $this->neighborhood->id,
        'title' => 'Hot courtyard',
        'severity_level' => 'high',
        'start_date' => now()->subHour()->format('Y-m-d\TH:i'),
    ])->assertRedirect(route('front.alerts.index'))->assertSessionHas('success', 'Report sent. It will appear on the map once an admin approves it.');

    expect(HeatAlert::sole()->review_status)->toBe(HeatAlert::REVIEW_PENDING);
});

it('publishes the alerts an admin creates without any review', function () {
    sendReport($this, $this->admin, 'Official alert')->assertCreated();

    expect(HeatAlert::sole()->review_status)->toBe(HeatAlert::REVIEW_APPROVED);
    $this->getJson(route('map.data'))->assertJsonCount(1, 'pins');
});

it('shows the admin the map, the list and the reports to review without create, edit or delete', function () {
    sendReport($this, $this->resident);
    $approved = HeatAlert::factory()->for($this->neighborhood)->create(['title' => 'Official heat wave']);

    $this->actingAs($this->admin)->get(route('admin.alerts.index'))
        ->assertOk()
        ->assertSee('Resident reports to review')
        ->assertSee('Courtyard without any shade')
        ->assertSee(route('admin.alerts.approve', HeatAlert::pendingReview()->sole()), false)
        ->assertSee('Official heat wave')
        ->assertDontSee('Add Alert')
        ->assertDontSee('id="map-report-toggle"', false)
        ->assertDontSee(route('admin.alerts.edit', $approved), false)
        ->assertDontSee('Delete this heat alert?');

    $this->actingAs($this->admin)->get(route('admin.alerts.show', $approved))->assertOk()->assertDontSee(route('admin.alerts.edit', $approved), false);
});

it('no longer offers an Add an alert button on the public heat alerts page', function () {
    $this->actingAs($this->resident)->get(route('front.alerts.index'))->assertOk()->assertDontSee('Add an alert')->assertSee('id="map-report-toggle"', false);
    $this->actingAs($this->admin)->get(route('front.alerts.index'))->assertOk()->assertDontSee('Add an alert');
});
