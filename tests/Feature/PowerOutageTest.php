<?php

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
    PowerOutage::factory()->create([
        'title' => 'North side outage',
        'area' => 'North avenue',
        'status' => 'planned',
        'severity' => 'moderate',
    ]);

    $response = $this->get(route('front.power-outages.index'));

    $response->assertOk()
        ->assertSee('Power outages')
        ->assertSee('North side outage');
});

test('admin can create a power outage', function () {
    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.power-outages.store'), [
            'title' => 'Campus blackout',
            'area' => 'Campus block B',
            'status' => 'in_progress',
            'severity' => 'high',
            'started_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'estimated_end_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'description' => 'A transformer issue has interrupted building B.',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('power_outages', ['title' => 'Campus blackout']);
});

test('admin can update a power outage', function () {
    $powerOutage = PowerOutage::factory()->create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.power-outages.update', $powerOutage), [
            'title' => 'Updated outage',
            'area' => 'North district',
            'status' => 'resolved',
            'severity' => 'low',
            'started_at' => '2026-10-05T08:00',
            'estimated_end_at' => '2026-10-05T10:00',
            'description' => 'Power service was restored.',
        ]);

    $response->assertRedirect(route('admin.power-outages.show', $powerOutage));
    $this->assertDatabaseHas('power_outages', [
        'id' => $powerOutage->id,
        'title' => 'Updated outage',
        'area' => 'North district',
        'status' => 'resolved',
        'severity' => 'low',
    ]);
});

test('admin cannot create a power outage with an invalid status', function () {
    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.power-outages.store'), [
            'title' => 'Invalid outage',
            'area' => 'West district',
            'status' => 'unknown',
            'severity' => 'high',
        ]);

    $response->assertSessionHasErrors(['status']);
    $this->assertDatabaseMissing('power_outages', ['title' => 'Invalid outage']);
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
