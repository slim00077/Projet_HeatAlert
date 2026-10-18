<?php

use App\Models\HeatAlert;
use App\Models\Neighborhood;
use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\User;
use App\Notifications\HeatAlertIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->marsa = Neighborhood::factory()->create(['name' => 'La Marsa']);
    $this->ariana = Neighborhood::factory()->create(['name' => 'Ariana']);
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager($this->marsa)->create(['name' => 'Sami']);
    $this->resident = User::factory()->create(['name' => 'Amine']);
});

function userPayload(User $user, array $overrides = []): array
{
    return array_merge(['name' => $user->name, 'email' => $user->email, 'role' => $user->role], $overrides);
}

function residencePayload(array $overrides = []): array
{
    return array_merge(['name' => 'Les Dunes', 'address' => '3 Rue de la Corniche', 'neighborhood' => 'La Marsa'], $overrides);
}

it('only lets an admin change roles and assign a neighborhood to a manager', function () {
    $this->actingAs($this->resident)->put(route('admin.users.update', $this->resident), ['role' => 'admin'])->assertForbidden();
    $this->actingAs($this->manager)->get(route('admin.users.edit', $this->resident))->assertForbidden();

    $this->actingAs($this->admin)->put(route('admin.users.update', $this->resident), userPayload($this->resident, ['role' => 'manager']))
        ->assertSessionHasErrors('neighborhood_id');

    $this->actingAs($this->admin)->put(route('admin.users.update', $this->resident), userPayload($this->resident, ['role' => 'manager', 'neighborhood_id' => $this->ariana->id]))
        ->assertRedirect(route('admin.users.index'));

    expect($this->resident->fresh())->role->toBe('manager')->neighborhood_id->toBe($this->ariana->id);

    $this->actingAs($this->admin)->put(route('admin.users.update', $this->resident), userPayload($this->resident, ['role' => 'resident']));
    expect($this->resident->fresh())->role->toBe('resident')->neighborhood_id->toBeNull();
});

it('stops an admin from removing their own admin access', function () {
    $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), userPayload($this->admin, ['role' => 'resident']))
        ->assertSessionHasErrors('role');

    expect($this->admin->fresh()->isAdmin())->toBeTrue();
});

it('creates a pending residence for a manager in their own neighborhood only', function () {
    $this->actingAs($this->manager)->post(route('residences.store'), residencePayload())
        ->assertRedirect(route('residences.index'));

    $residence = Residence::sole();
    expect($residence->status)->toBe(Residence::STATUS_PENDING)
        ->and($residence->user_id)->toBe($this->manager->id);

    $this->actingAs($this->manager)->post(route('residences.store'), residencePayload(['neighborhood' => 'Ariana']))
        ->assertSessionHasErrors('neighborhood');

    expect(Residence::count())->toBe(1);
});

it('does not let a manager without a neighborhood create residences', function () {
    $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);

    $this->actingAs($manager)->post(route('residences.store'), residencePayload())->assertRedirect(route('residences.index'))->assertSessionHas('error');
    expect(Residence::count())->toBe(0);
});

it('lets the admin approve or reject with a reason', function () {
    $pending = Residence::factory()->pending()->create(['user_id' => $this->manager->id, 'neighborhood' => 'La Marsa']);
    $other = Residence::factory()->pending()->create(['user_id' => $this->manager->id, 'neighborhood' => 'La Marsa']);

    $this->actingAs($this->manager)->post(route('admin.approvals.approve', $pending))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.approvals.index'))->assertOk()->assertSee($pending->name);

    $this->actingAs($this->admin)->post(route('admin.approvals.approve', $pending))->assertRedirect();
    expect($pending->fresh())->status->toBe('approved')->reviewed_by->toBe($this->admin->id);

    $this->actingAs($this->admin)->post(route('admin.approvals.reject', $other), [])->assertSessionHasErrors('rejection_reason');
    $this->actingAs($this->admin)->post(route('admin.approvals.reject', $other), ['rejection_reason' => 'Wrong address'])->assertRedirect();
    expect($other->fresh())->status->toBe('rejected')->rejection_reason->toBe('Wrong address');
});

it('lets a manager fix a rejected residence and resubmit it', function () {
    $residence = Residence::factory()->rejected()->create(['user_id' => $this->manager->id, 'neighborhood' => 'La Marsa']);

    $this->actingAs($this->manager)->get(route('residences.show', $residence))->assertOk()->assertSee('Address could not be verified.');

    $this->actingAs($this->manager)->put(route('residences.update', $residence), residencePayload(['address' => 'New address']))
        ->assertRedirect(route('residences.index'));

    expect($residence->fresh())->status->toBe('pending')->rejection_reason->toBeNull();
});

it('only lets a manager add residents to their own approved residences', function () {
    $approved = Residence::factory()->create(['user_id' => $this->manager->id, 'neighborhood' => 'La Marsa']);
    $pending = Residence::factory()->pending()->create(['user_id' => $this->manager->id, 'neighborhood' => 'La Marsa']);
    $foreign = Residence::factory()->create(['user_id' => User::factory()->manager($this->ariana)->create()->id]);

    $this->actingAs($this->manager)->post(route('residences.members.store', $approved), ['email' => $this->resident->email])
        ->assertSessionHas('success');
    expect($approved->members()->pluck('users.id')->all())->toBe([$this->resident->id]);

    $this->actingAs($this->manager)->post(route('residences.members.store', $pending), ['email' => $this->resident->email])->assertForbidden();
    $this->actingAs($this->manager)->post(route('residences.members.store', $foreign), ['email' => $this->resident->email])->assertForbidden();
    $this->actingAs($this->resident)->post(route('residences.members.store', $approved), ['email' => $this->resident->email])->assertForbidden();

    $this->actingAs($this->manager)->post(route('residences.members.store', $approved), ['email' => $this->resident->email])
        ->assertSessionHasErrors('email');
    $this->actingAs($this->manager)->post(route('residences.members.store', $approved), ['email' => 'nobody@example.com'])
        ->assertSessionHasErrors('email');
    $this->actingAs($this->manager)->post(route('residences.members.store', $approved), ['email' => $this->admin->email])
        ->assertSessionHasErrors('email');

    $this->actingAs($this->manager)->delete(route('residences.members.destroy', [$approved, $this->resident]))->assertSessionHas('success');
    expect($approved->members()->count())->toBe(0);
});

it('shows residents the approved residences they were added to and lets them report issues there', function () {
    $residence = Residence::factory()->create(['user_id' => $this->manager->id, 'neighborhood' => 'La Marsa', 'address' => '77 Member Street']);
    $hidden = Residence::factory()->create(['user_id' => $this->manager->id, 'address' => '88 Hidden Street']);
    $pending = Residence::factory()->pending()->create(['user_id' => $this->manager->id, 'address' => '99 Pending Street']);
    $pending->members()->attach($this->resident);
    $residence->members()->attach($this->resident);

    $this->actingAs($this->resident)->get(route('residences.index'))
        ->assertSee('77 Member Street')->assertDontSee('88 Hidden Street')->assertDontSee('99 Pending Street');

    $this->actingAs($this->resident)->get(route('residences.show', $residence))->assertOk();
    $this->actingAs($this->resident)->get(route('residences.show', $hidden))->assertForbidden();
    $this->actingAs($this->resident)->get(route('residences.show', $pending))->assertForbidden();
    $this->actingAs($this->resident)->get(route('residences.edit', $residence))->assertForbidden();

    $this->actingAs($this->resident)->post(route('resident-reports.store'), [
        'residence_id' => $residence->id, 'title' => 'No AC', 'description' => 'Broken', 'reported_at' => '2026-10-01',
    ])->assertRedirect(route('resident-reports.index'));
    expect(ResidentReport::count())->toBe(1);
});

it('keeps managers out of resident reports and the admin area', function () {
    $this->actingAs($this->manager)->get(route('resident-reports.index'))->assertForbidden();
    $this->actingAs($this->manager)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($this->manager)->get(route('admin.approvals.index'))->assertForbidden();
});

it('stops a manager from deleting an approved residence but not a pending one', function () {
    $approved = Residence::factory()->create(['user_id' => $this->manager->id]);
    $pending = Residence::factory()->pending()->create(['user_id' => $this->manager->id]);

    $this->actingAs($this->manager)->delete(route('residences.destroy', $approved))->assertForbidden();
    $this->actingAs($this->manager)->delete(route('residences.destroy', $pending))->assertRedirect(route('residences.index'));
    expect(Residence::count())->toBe(1);
});

it('only counts approved residences and members for map data and notifications', function () {
    Notification::fake();
    $member = User::factory()->create();
    $approved = Residence::factory()->create(['user_id' => $this->manager->id, 'neighborhood' => 'La Marsa']);
    $approved->members()->attach($member);
    $pendingOwner = User::factory()->manager($this->marsa)->create();
    Residence::factory()->pending()->create(['user_id' => $pendingOwner->id, 'neighborhood' => 'La Marsa']);

    expect($this->marsa->residences()->count())->toBe(1);

    $alert = HeatAlert::factory()->for($this->marsa)->create(['severity_level' => 'high']);
    $alert->notifyResidents();

    Notification::assertSentTo([$this->manager, $member], HeatAlertIssued::class);
    Notification::assertNotSentTo($pendingOwner, HeatAlertIssued::class);
});

it('shows each role the right navigation tabs', function () {
    $this->get('/')->assertDontSee('My Residences')->assertDontSee('Managed Residences')->assertSee('Heat Alerts')->assertSee('Login');

    $this->actingAs($this->resident)->get('/')
        ->assertSee('My Residences')->assertSee('My Reports')->assertDontSee('Managed Residences')->assertDontSee('>Admin');

    $this->actingAs($this->manager)->get('/')
        ->assertSee('Managed Residences')->assertDontSee('My Reports')->assertDontSee('My Residences');

    Residence::factory()->pending()->create(['user_id' => $this->manager->id]);
    $this->actingAs($this->admin)->get('/')
        ->assertSee('Admin')->assertDontSee('My Residences')->assertDontSee('Managed Residences')->assertDontSee('My Reports');

    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('Approvals')->assertSee('Neighborhoods');
});
