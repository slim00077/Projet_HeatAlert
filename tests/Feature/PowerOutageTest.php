<?php

use App\Models\Neighborhood;
use App\Models\OutageReport;
use App\Models\PowerOutage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest cannot access power outage management', function () {
    $response = $this->get(route('admin.power-outages.index'));

    $response->assertRedirect(route('login'));
});

test('resident cannot access power outage management', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('admin.power-outages.index'));

    $response->assertForbidden();
});

test('front-office outage list is available', function () {
    $neighborhood = Neighborhood::factory()->create([
        'name' => 'North avenue',
        'latitude' => 36.82,
        'longitude' => 10.2,
        'population' => 2500,
        'vulnerable_population' => 300,
    ]);
    $powerOutage = PowerOutage::factory()->create([
        'title' => 'North side outage',
        'status' => 'planned',
        'severity' => 'moderate',
    ]);
    $powerOutage->neighborhoods()->attach($neighborhood);

    $response = $this->get(route('front.power-outages.index'));

    $response->assertOk()
        ->assertSee('Power outages')
        ->assertSee('North side outage')
        ->assertSee('North avenue')
        ->assertSee('data-outage-map')
        ->assertSee('Estimated population:')
        ->assertSee('2,500')
        ->assertSee('vulnerable residents')
        ->assertViewHas('estimatedVulnerablePopulation', 300);
});

test('public outage list separates active alerts from outage history', function () {
    PowerOutage::factory()->create(['title' => 'Scheduled interruption', 'status' => 'planned']);
    PowerOutage::factory()->create(['title' => 'Current interruption', 'status' => 'in_progress']);
    PowerOutage::factory()->create(['title' => 'Restored service', 'status' => 'resolved']);
    PowerOutage::factory()->create(['title' => 'Cancelled work', 'status' => 'cancelled']);

    $this->get(route('front.power-outages.index'))
        ->assertOk()
        ->assertSee('Scheduled interruption')
        ->assertSee('Current interruption')
        ->assertSee('Outages on the map')
        ->assertDontSee('Restored service')
        ->assertDontSee('Cancelled work')
        ->assertSee(route('front.power-outages.history'));

    $this->get(route('front.power-outages.history'))
        ->assertOk()
        ->assertSee('Restored service')
        ->assertSee('Cancelled work')
        ->assertDontSee('Scheduled interruption')
        ->assertDontSee('Current interruption');
});

test('outage detail map estimates impact for every affected neighborhood and maps those with coordinates', function () {
    $mappedNeighborhood = Neighborhood::factory()->create([
        'name' => 'Mapped district',
        'latitude' => 36.82,
        'longitude' => 10.2,
        'population' => 1500,
        'vulnerable_population' => 200,
    ]);
    $unmappedNeighborhood = Neighborhood::factory()->create([
        'name' => 'Unmapped district',
        'latitude' => null,
        'longitude' => null,
        'population' => 900,
        'vulnerable_population' => 100,
    ]);
    $powerOutage = PowerOutage::factory()->create(['title' => 'District power cut']);
    $powerOutage->neighborhoods()->attach([$mappedNeighborhood->id, $unmappedNeighborhood->id]);

    $this->get(route('front.power-outages.show', $powerOutage))
        ->assertOk()
        ->assertSee('Affected neighborhoods')
        ->assertSee('2,400')
        ->assertSee('affected neighborhoods')
        ->assertSee('shown on map')
        ->assertSee('vulnerable residents')
        ->assertSee('Mapped district')
        ->assertSee('Unmapped district')
        ->assertViewHas('affectedNeighborhoods', 2)
        ->assertViewHas('mappedNeighborhoods', 1)
        ->assertViewHas('estimatedVulnerablePopulation', 300)
        ->assertViewHas('mapPoints', fn (array $mapPoints): bool => collect($mapPoints)->pluck('name')->all() === ['Mapped district']);
});

test('admin create form lists selectable neighborhoods', function () {
    $neighborhood = Neighborhood::factory()->create(['name' => 'Centre Ville']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.power-outages.create'))
        ->assertOk()
        ->assertSee('Affected neighborhoods')
        ->assertSee('Centre Ville')
        ->assertSee('neighborhood_ids[]');
});

test('admin can create a power outage', function () {
    $neighborhoods = Neighborhood::factory()->count(2)->create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.power-outages.store'), [
            'title' => 'Campus blackout',
            'status' => 'in_progress',
            'severity' => 'high',
            'neighborhood_ids' => $neighborhoods->modelKeys(),
            'started_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'estimated_end_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'description' => 'A transformer issue has interrupted building B.',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('power_outages', ['title' => 'Campus blackout']);
    $this->assertDatabaseCount('neighborhood_power_outage', 2);
});

test('admin must select at least one existing neighborhood for an outage', function () {
    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.power-outages.store'), [
            'title' => 'Campus blackout',
            'status' => 'in_progress',
            'severity' => 'high',
            'neighborhood_ids' => [],
            'started_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'estimated_end_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'description' => 'A transformer issue has interrupted building B.',
        ]);

    $response->assertSessionHasErrors(['neighborhood_ids']);
    $this->assertDatabaseMissing('power_outages', ['title' => 'Campus blackout']);
});

test('admin cannot associate an outage with a nonexistent neighborhood', function () {
    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.power-outages.store'), [
            'title' => 'Campus blackout',
            'status' => 'in_progress',
            'severity' => 'high',
            'neighborhood_ids' => [999999],
            'started_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'estimated_end_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'description' => 'A transformer issue has interrupted building B.',
        ]);

    $response->assertSessionHasErrors(['neighborhood_ids.0']);
    $this->assertDatabaseMissing('power_outages', ['title' => 'Campus blackout']);
});

test('admin cannot create a power outage with text below the minimum lengths', function () {
    $neighborhood = Neighborhood::factory()->create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.power-outages.store'), [
            'title' => 'Tiny',
            'status' => 'in_progress',
            'severity' => 'high',
            'neighborhood_ids' => [$neighborhood->id],
            'started_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'estimated_end_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'description' => 'Short',
        ]);

    $response->assertSessionHasErrors(['title', 'description']);
    $this->assertDatabaseMissing('power_outages', ['title' => 'Tiny']);
});

test('admin must provide a description and both outage dates', function () {
    $neighborhood = Neighborhood::factory()->create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.power-outages.store'), [
            'title' => 'Campus blackout',
            'status' => 'in_progress',
            'severity' => 'high',
            'neighborhood_ids' => [$neighborhood->id],
        ]);

    $response->assertSessionHasErrors(['description', 'started_at', 'estimated_end_at']);
    $this->assertDatabaseMissing('power_outages', ['title' => 'Campus blackout']);
});

test('admin must set an estimated end after the outage start', function () {
    $neighborhood = Neighborhood::factory()->create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.power-outages.store'), [
            'title' => 'Campus blackout',
            'status' => 'in_progress',
            'severity' => 'high',
            'neighborhood_ids' => [$neighborhood->id],
            'started_at' => '2026-10-05T08:00',
            'estimated_end_at' => '2026-10-05T08:00',
            'description' => 'A transformer issue has interrupted building B.',
        ]);

    $response->assertSessionHasErrors([
        'estimated_end_at' => 'The estimated end at field must be a date after started at.',
    ]);
    $this->assertDatabaseMissing('power_outages', ['title' => 'Campus blackout']);
});

test('admin can update a power outage', function () {
    $neighborhood = Neighborhood::factory()->create();
    $powerOutage = PowerOutage::factory()->create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.power-outages.update', $powerOutage), [
            'title' => 'Updated outage',
            'status' => 'resolved',
            'severity' => 'low',
            'neighborhood_ids' => [$neighborhood->id],
            'started_at' => '2026-10-05T08:00',
            'estimated_end_at' => '2026-10-05T10:00',
            'description' => 'Power service was restored.',
        ]);

    $response->assertRedirect(route('admin.power-outages.show', $powerOutage));
    $this->assertDatabaseHas('power_outages', [
        'id' => $powerOutage->id,
        'title' => 'Updated outage',
        'status' => 'resolved',
        'severity' => 'low',
    ]);
    $this->assertDatabaseHas('neighborhood_power_outage', [
        'power_outage_id' => $powerOutage->id,
        'neighborhood_id' => $neighborhood->id,
    ]);
});

test('admin cannot update a power outage with text below the minimum lengths', function () {
    $neighborhood = Neighborhood::factory()->create();
    $powerOutage = PowerOutage::factory()->create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.power-outages.update', $powerOutage), [
            'title' => 'Tiny',
            'status' => 'in_progress',
            'severity' => 'high',
            'neighborhood_ids' => [$neighborhood->id],
            'started_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'estimated_end_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'description' => 'Short',
        ]);

    $response->assertSessionHasErrors(['title', 'description']);
    $this->assertDatabaseHas('power_outages', [
        'id' => $powerOutage->id,
        'title' => $powerOutage->title,
    ]);
});

test('admin cannot update a power outage with an estimated end before its start', function () {
    $neighborhood = Neighborhood::factory()->create();
    $powerOutage = PowerOutage::factory()->create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.power-outages.update', $powerOutage), [
            'title' => 'Updated outage',
            'status' => 'in_progress',
            'severity' => 'high',
            'neighborhood_ids' => [$neighborhood->id],
            'started_at' => '2026-10-05T10:00',
            'estimated_end_at' => '2026-10-05T09:00',
            'description' => 'A transformer issue has interrupted building B.',
        ]);

    $response->assertSessionHasErrors([
        'estimated_end_at' => 'The estimated end at field must be a date after started at.',
    ]);
    $this->assertDatabaseHas('power_outages', [
        'id' => $powerOutage->id,
        'title' => $powerOutage->title,
    ]);
});

test('admin cannot create a power outage with an invalid status', function () {
    $neighborhood = Neighborhood::factory()->create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.power-outages.store'), [
            'title' => 'Invalid outage',
            'status' => 'unknown',
            'severity' => 'high',
            'neighborhood_ids' => [$neighborhood->id],
            'started_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'estimated_end_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'description' => 'An outage details description.',
        ]);

    $response->assertSessionHasErrors(['status']);
    $this->assertDatabaseMissing('power_outages', ['title' => 'Invalid outage']);
});

test('admin can cancel a planned outage and cancelled outages cannot be reopened', function () {
    $neighborhood = Neighborhood::factory()->create();
    $powerOutage = PowerOutage::factory()->create(['status' => 'planned']);
    $powerOutage->neighborhoods()->attach($neighborhood);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.power-outages.update', $powerOutage), [
            'title' => $powerOutage->title,
            'status' => 'cancelled',
            'severity' => $powerOutage->severity,
            'neighborhood_ids' => [$neighborhood->id],
            'started_at' => $powerOutage->started_at->format('Y-m-d\TH:i'),
            'estimated_end_at' => $powerOutage->estimated_end_at->format('Y-m-d\TH:i'),
            'description' => $powerOutage->description,
        ])
        ->assertRedirect(route('admin.power-outages.show', $powerOutage));

    $this->assertDatabaseHas('power_outages', [
        'id' => $powerOutage->id,
        'status' => 'cancelled',
    ]);

    $this->put(route('admin.power-outages.update', $powerOutage), [
        'title' => $powerOutage->title,
        'status' => 'planned',
        'severity' => $powerOutage->severity,
        'neighborhood_ids' => [$neighborhood->id],
        'started_at' => $powerOutage->started_at->format('Y-m-d\TH:i'),
        'estimated_end_at' => $powerOutage->estimated_end_at->format('Y-m-d\TH:i'),
        'description' => $powerOutage->description,
    ])
        ->assertSessionHasErrors([
            'status' => 'Choose a valid status transition for this outage.',
        ]);
});

test('admin can advance a planned outage through in-progress to resolved', function () {
    $neighborhood = Neighborhood::factory()->create();
    $powerOutage = PowerOutage::factory()->create(['status' => 'planned']);
    $powerOutage->neighborhoods()->attach($neighborhood);
    $admin = User::factory()->admin()->create();
    $attributes = [
        'title' => $powerOutage->title,
        'severity' => $powerOutage->severity,
        'neighborhood_ids' => [$neighborhood->id],
        'started_at' => $powerOutage->started_at->format('Y-m-d\TH:i'),
        'estimated_end_at' => $powerOutage->estimated_end_at->format('Y-m-d\TH:i'),
        'description' => $powerOutage->description,
    ];

    $this->actingAs($admin)
        ->put(route('admin.power-outages.update', $powerOutage), [
            ...$attributes,
            'status' => 'in_progress',
        ])
        ->assertRedirect(route('admin.power-outages.show', $powerOutage));

    $this->assertDatabaseHas('power_outages', [
        'id' => $powerOutage->id,
        'status' => 'in_progress',
    ]);

    $this->put(route('admin.power-outages.update', $powerOutage), [
        ...$attributes,
        'status' => 'resolved',
    ])
        ->assertRedirect(route('admin.power-outages.show', $powerOutage));

    $this->assertDatabaseHas('power_outages', [
        'id' => $powerOutage->id,
        'status' => 'resolved',
    ]);
});

test('admin cannot cancel an outage after it has started', function () {
    $neighborhood = Neighborhood::factory()->create();
    $powerOutage = PowerOutage::factory()->create(['status' => 'in_progress']);
    $powerOutage->neighborhoods()->attach($neighborhood);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.power-outages.update', $powerOutage), [
            'title' => $powerOutage->title,
            'status' => 'cancelled',
            'severity' => $powerOutage->severity,
            'neighborhood_ids' => [$neighborhood->id],
            'started_at' => $powerOutage->started_at->format('Y-m-d\TH:i'),
            'estimated_end_at' => $powerOutage->estimated_end_at->format('Y-m-d\TH:i'),
            'description' => $powerOutage->description,
        ])
        ->assertSessionHasErrors([
            'status' => 'Choose a valid status transition for this outage.',
        ]);

    $this->assertDatabaseHas('power_outages', [
        'id' => $powerOutage->id,
        'status' => 'in_progress',
    ]);
});

test('resolved outage status cannot be changed', function () {
    $neighborhood = Neighborhood::factory()->create();
    $powerOutage = PowerOutage::factory()->create(['status' => 'resolved']);
    $powerOutage->neighborhoods()->attach($neighborhood);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.power-outages.update', $powerOutage), [
            'title' => $powerOutage->title,
            'status' => 'in_progress',
            'severity' => $powerOutage->severity,
            'neighborhood_ids' => [$neighborhood->id],
            'started_at' => $powerOutage->started_at->format('Y-m-d\TH:i'),
            'estimated_end_at' => $powerOutage->estimated_end_at->format('Y-m-d\TH:i'),
            'description' => $powerOutage->description,
        ])
        ->assertSessionHasErrors([
            'status' => 'Choose a valid status transition for this outage.',
        ]);

    $this->assertDatabaseHas('power_outages', [
        'id' => $powerOutage->id,
        'status' => 'resolved',
    ]);
});

test('admin can delete a power outage and its reports', function () {
    $powerOutage = PowerOutage::factory()
        ->has(OutageReport::factory()->count(2), 'reports')
        ->create();
    $reportIds = $powerOutage->reports()->pluck('id');

    $response = $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.power-outages.destroy', $powerOutage));

    $response->assertRedirect(route('admin.power-outages.index'));
    $this->assertDatabaseMissing('power_outages', ['id' => $powerOutage->id]);

    foreach ($reportIds as $reportId) {
        $this->assertDatabaseMissing('outage_reports', ['id' => $reportId]);
    }
});

test('guest cannot report an outage', function () {
    $powerOutage = PowerOutage::factory()->create();

    $response = $this->post(route('front.power-outages.reports.store', $powerOutage), [
        'description' => 'The elevators and hallway lights are without power.',
    ]);

    $response->assertRedirect(route('login'));
    $this->assertDatabaseCount('outage_reports', 0);
    $this->get(route('my-outage-reports.index'))->assertRedirect(route('login'));
});

test('signed-in user report is linked to their account and ignores forged ownership fields', function () {
    $powerOutage = PowerOutage::factory()->create();
    $otherPowerOutage = PowerOutage::factory()->create();
    $user = User::factory()->create(['name' => 'Jamie Resident', 'email' => 'jamie@example.com']);
    $forgedUser = User::factory()->create();

    $response = $this->actingAs($user)->post(route('front.power-outages.reports.store', $powerOutage), [
        'power_outage_id' => $otherPowerOutage->id,
        'user_id' => $forgedUser->id,
        'reporter_name' => 'Forged Reporter',
        'email' => 'forged@example.com',
        'description' => 'The elevators and hallway lights are without power.',
        'status' => 'verified',
    ]);

    $response->assertRedirect(route('front.power-outages.show', $powerOutage));
    $this->assertDatabaseHas('outage_reports', [
        'power_outage_id' => $powerOutage->id,
        'user_id' => $user->id,
        'reporter_name' => 'Jamie Resident',
        'email' => 'jamie@example.com',
        'status' => 'pending',
    ]);
    $this->assertDatabaseMissing('outage_reports', [
        'power_outage_id' => $otherPowerOutage->id,
        'reporter_name' => 'Jamie Resident',
    ]);
});

test('user can only see and update their own outage reports', function () {
    $powerOutage = PowerOutage::factory()->create();
    $otherOutage = PowerOutage::factory()->create();
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $ownReport = OutageReport::factory()->for($powerOutage)->for($user)->create([
        'description' => 'My original report describes the outage clearly.',
        'status' => 'verified',
    ]);
    $otherReport = OutageReport::factory()->for($otherOutage)->for($otherUser)->create([
        'description' => 'This message belongs to somebody else.',
    ]);

    $response = $this->actingAs($user)->get(route('my-outage-reports.index'));

    $response->assertOk()
        ->assertSee($ownReport->description)
        ->assertDontSee($otherReport->description);

    $update = $this->patch(route('my-outage-reports.update', $ownReport), [
        'description' => 'My updated report keeps status under admin control.',
        'status' => 'closed',
        'user_id' => $otherUser->id,
        'power_outage_id' => $otherOutage->id,
    ]);

    $update->assertRedirect(route('my-outage-reports.index'));
    $this->assertDatabaseHas('outage_reports', [
        'id' => $ownReport->id,
        'user_id' => $user->id,
        'power_outage_id' => $powerOutage->id,
        'description' => 'My updated report keeps status under admin control.',
        'status' => 'verified',
    ]);
});

test('only the report owner sees update and delete controls on outage details', function () {
    $powerOutage = PowerOutage::factory()->create();
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $report = OutageReport::factory()->for($powerOutage)->for($owner)->create();

    $this->actingAs($owner)
        ->get(route('front.power-outages.show', $powerOutage))
        ->assertOk()
        ->assertSee(route('my-outage-reports.update', $report))
        ->assertSee(route('my-outage-reports.destroy', $report));

    $this->actingAs($otherUser)
        ->get(route('front.power-outages.show', $powerOutage))
        ->assertOk()
        ->assertDontSee(route('my-outage-reports.update', $report))
        ->assertDontSee(route('my-outage-reports.destroy', $report));
});

test('user cannot update another users outage report', function () {
    $powerOutage = PowerOutage::factory()->create();
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $report = OutageReport::factory()->for($powerOutage)->for($owner)->create([
        'description' => 'The owner must remain the only person who can change this.',
    ]);

    $response = $this->actingAs($user)->patch(route('my-outage-reports.update', $report), [
        'description' => 'Unauthorized changed report description.',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('outage_reports', [
        'id' => $report->id,
        'user_id' => $owner->id,
        'description' => 'The owner must remain the only person who can change this.',
    ]);
});

test('user can delete their own outage report', function () {
    $powerOutage = PowerOutage::factory()->create();
    $user = User::factory()->create();
    $report = OutageReport::factory()->for($powerOutage)->for($user)->create();

    $response = $this->actingAs($user)->delete(route('my-outage-reports.destroy', $report));

    $response->assertRedirect(route('my-outage-reports.index'));
    $this->assertDatabaseMissing('outage_reports', ['id' => $report->id]);
    $this->assertModelExists($powerOutage);
});

test('user cannot delete another users outage report', function () {
    $powerOutage = PowerOutage::factory()->create();
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $report = OutageReport::factory()->for($powerOutage)->for($owner)->create();

    $response = $this->actingAs($user)->delete(route('my-outage-reports.destroy', $report));

    $response->assertForbidden();
    $this->assertModelExists($report);
});

test('admin can review report details on the outage page', function () {
    $powerOutage = PowerOutage::factory()->create();
    $report = OutageReport::factory()->for($powerOutage)->create([
        'description' => 'The apartment building has been without power since noon.',
    ]);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.power-outages.show', $powerOutage));

    $response->assertOk()
        ->assertSee($report->description);
});

test('admin can update report status but cannot edit submitted report details', function () {
    $powerOutage = PowerOutage::factory()->create();
    $otherPowerOutage = PowerOutage::factory()->create();
    $report = OutageReport::factory()->for($powerOutage)->create([
        'reporter_name' => 'Original Resident',
        'email' => 'original@example.com',
        'description' => 'The original report message must remain unchanged.',
        'status' => 'pending',
    ]);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.outage-reports.status', $report), [
            'power_outage_id' => $otherPowerOutage->id,
            'reporter_name' => 'Forged Resident',
            'email' => 'forged@example.com',
            'description' => 'The submitted description must not be modifiable.',
            'status' => 'verified',
        ]);

    $response->assertRedirect(route('admin.power-outages.show', $powerOutage));
    $this->assertDatabaseHas('outage_reports', [
        'id' => $report->id,
        'power_outage_id' => $powerOutage->id,
        'reporter_name' => 'Original Resident',
        'email' => 'original@example.com',
        'description' => 'The original report message must remain unchanged.',
        'status' => 'verified',
    ]);
});

test('admin cannot set an unsupported report status', function () {
    $powerOutage = PowerOutage::factory()->create();
    $report = OutageReport::factory()->for($powerOutage)->create(['status' => 'pending']);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.outage-reports.status', $report), ['status' => 'deleted']);

    $response->assertSessionHasErrors(['status']);
    $this->assertDatabaseHas('outage_reports', ['id' => $report->id, 'status' => 'pending']);
});

test('resident cannot update outage report status', function () {
    $powerOutage = PowerOutage::factory()->create();
    $report = OutageReport::factory()->for($powerOutage)->create();

    $response = $this->actingAs(User::factory()->create())
        ->patch(route('admin.outage-reports.status', $report), ['status' => 'verified']);

    $response->assertForbidden();
    $this->assertDatabaseHas('outage_reports', ['id' => $report->id, 'status' => 'pending']);
});

test('admin can delete a report without deleting its power outage', function () {
    $powerOutage = PowerOutage::factory()->create();
    $report = OutageReport::factory()->for($powerOutage)->create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.outage-reports.destroy', $report));

    $response->assertRedirect(route('admin.power-outages.show', $powerOutage));
    $this->assertDatabaseMissing('outage_reports', ['id' => $report->id]);
    $this->assertModelExists($powerOutage);
});
