<?php

use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->resident = User::factory()->create();
    $this->other = User::factory()->create();
    $this->admin = User::factory()->admin()->create();
    $this->residence = Residence::factory()->create(['user_id' => $this->resident->id]);
    $this->otherResidence = Residence::factory()->create(['user_id' => $this->other->id]);
});

it('registers new users as residents', function () {
    $this->post('/register', [
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('residences.index'));

    expect(User::where('email', 'new@example.com')->first()->role)->toBe('resident');
});

it('redirects guests to login', function () {
    $this->get('/residences')->assertRedirect('/login');
});

it('forbids residents from the back office', function () {
    $this->actingAs($this->resident)->get('/admin')->assertForbidden();
    $this->actingAs($this->resident)->get('/admin/users')->assertForbidden();
    $this->actingAs($this->admin)->get('/admin')->assertOk();
    $this->actingAs($this->admin)->get('/admin/users')->assertOk();
});

it('only lists and shows own residences', function () {
    $this->actingAs($this->resident)->get('/residences')
        ->assertOk()->assertSee($this->residence->address)->assertDontSee($this->otherResidence->address);

    $this->actingAs($this->resident)->get(route('residences.show', $this->otherResidence))->assertForbidden();
    $this->actingAs($this->resident)->get(route('residences.edit', $this->otherResidence))->assertForbidden();
    $this->actingAs($this->resident)->put(route('residences.update', $this->otherResidence), [
        'name' => 'x', 'address' => 'x', 'neighborhood' => 'x',
    ])->assertForbidden();
    $this->actingAs($this->resident)->delete(route('residences.destroy', $this->otherResidence))->assertForbidden();
});

it('creates residences for the logged-in resident only', function () {
    $this->actingAs($this->resident)->post('/residences', [
        'name' => 'Mine', 'address' => '1 Street', 'neighborhood' => 'Centre', 'user_id' => $this->other->id,
    ])->assertRedirect(route('residences.index'))->assertSessionHas('success', 'Residence created successfully.');

    expect(Residence::where('name', 'Mine')->first()->user_id)->toBe($this->resident->id);
});

it('searches residences', function () {
    $this->actingAs($this->admin)->get('/residences?search='.urlencode($this->residence->neighborhood))
        ->assertOk()->assertSee($this->residence->address);
});

it('refuses reports on residences owned by someone else', function () {
    $this->actingAs($this->resident)->post('/resident-reports', [
        'residence_id' => $this->otherResidence->id,
        'title' => 'T', 'description' => 'D', 'reported_at' => '2026-10-01',
    ])->assertSessionHasErrors('residence_id');

    expect(ResidentReport::count())->toBe(0);
});

it('creates reports on own residences as pending', function () {
    $this->actingAs($this->resident)->post('/resident-reports', [
        'residence_id' => $this->residence->id,
        'title' => 'T', 'description' => 'D', 'reported_at' => '2026-10-01', 'status' => 'Resolved',
    ])->assertRedirect(route('resident-reports.index'));

    $report = ResidentReport::first();
    expect($report->user_id)->toBe($this->resident->id)->and($report->status)->toBe('Pending');
});

it('hides and protects other users reports', function () {
    $report = ResidentReport::factory()->create(['user_id' => $this->other->id, 'residence_id' => $this->otherResidence->id]);

    $this->actingAs($this->resident)->get(route('resident-reports.show', $report))->assertForbidden();
    $this->actingAs($this->resident)->delete(route('resident-reports.destroy', $report))->assertForbidden();
    $this->actingAs($this->resident)->get('/resident-reports')->assertDontSee($report->title);
});

it('filters reports by status', function () {
    ResidentReport::factory()->create(['user_id' => $this->resident->id, 'residence_id' => $this->residence->id, 'status' => 'Pending', 'title' => 'PendingOne']);
    ResidentReport::factory()->create(['user_id' => $this->resident->id, 'residence_id' => $this->residence->id, 'status' => 'Resolved', 'title' => 'ResolvedOne']);

    $this->actingAs($this->resident)->get('/resident-reports?status=Pending')
        ->assertSee('PendingOne')->assertDontSee('ResolvedOne');
});

it('lets admins change report status', function () {
    $report = ResidentReport::factory()->create(['user_id' => $this->resident->id, 'residence_id' => $this->residence->id, 'status' => 'Pending']);

    $this->actingAs($this->admin)->put(route('resident-reports.update', $report), [
        'residence_id' => $this->residence->id, 'title' => 'T', 'description' => 'D',
        'status' => 'Resolved', 'reported_at' => '2026-10-01',
    ])->assertRedirect();

    expect($report->fresh()->status)->toBe('Resolved');
});

it('renders the redesigned pages', function () {
    $report = ResidentReport::factory()->create(['user_id' => $this->resident->id, 'residence_id' => $this->residence->id]);

    $this->get('/')->assertOk();
    $this->get('/login')->assertOk();
    $this->get('/register')->assertOk();

    foreach (['/residences', '/resident-reports', '/residences/create', '/resident-reports/create', route('residences.show', $this->residence), route('resident-reports.show', $report), route('resident-reports.edit', $report)] as $url) {
        $this->actingAs($this->resident)->get($url)->assertOk();
        $this->actingAs($this->admin)->get($url)->assertOk();
    }

    $this->actingAs($this->admin)->get('/resident-reports?search='.urlencode($report->title))->assertOk()->assertSee($report->title);
});
