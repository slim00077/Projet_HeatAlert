<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('residents cannot manage users', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.users.index'))->assertForbidden();
});

test('admin can open the users and settings pages', function () {
    $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('admin.users.create'))->assertOk();
    $this->actingAs($this->admin)->get(route('admin.settings'))->assertOk()->assertSee('data-theme-option', false);
});

test('admin can create a user with a role', function () {
    $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'New Admin',
        'email' => 'new@example.com',
        'role' => 'admin',
        'password' => 'secret-pass',
        'password_confirmation' => 'secret-pass',
    ])->assertRedirect(route('admin.users.index'));

    expect(User::where('email', 'new@example.com')->first()->isAdmin())->toBeTrue();
});

test('admin can edit a user and keep the password when left blank', function () {
    $user = User::factory()->create();
    $oldHash = $user->password;

    $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'name' => 'Renamed',
        'email' => $user->email,
        'role' => 'resident',
    ])->assertRedirect(route('admin.users.index'));

    expect($user->fresh()->name)->toBe('Renamed')->and($user->fresh()->password)->toBe($oldHash);
});

test('admin can delete another user but not themselves', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));
    $this->assertModelMissing($user);

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin))->assertSessionHasErrors('user');
    $this->assertModelExists($this->admin);
});

test('admin cannot remove their own admin role', function () {
    $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
        'name' => $this->admin->name,
        'email' => $this->admin->email,
        'role' => 'resident',
    ])->assertSessionHasErrors('role');

    expect($this->admin->fresh()->isAdmin())->toBeTrue();
});
