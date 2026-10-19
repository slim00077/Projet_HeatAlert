<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PragmaRX\Google2FA\Google2FA;

function confirmedPassword(): array
{
    return ['auth.password_confirmed_at' => time()];
}

function currentOtp(User $user, int $steps = 0): string
{
    $engine = app(Google2FA::class);

    return $engine->oathTotp(decrypt($user->two_factor_secret), $engine->getTimestamp() + $steps);
}

it('registers a resident and sends a verification email', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'New Resident', 'email' => 'new@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
    ])->assertRedirect(route('residences.index'));

    $user = User::where('email', 'new@example.com')->firstOrFail();
    expect($user->role)->toBe('resident')->and($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects weak registrations with clear errors', function () {
    $this->post('/register', ['name' => '', 'email' => 'bad', 'password' => 'short', 'password_confirmation' => 'other'])
        ->assertSessionHasErrors(['name', 'email', 'password']);
});

it('keeps unverified users out of the app until they verify their email', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('residences.index'))->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get(route('verification.notice'))->assertOk()->assertSee('Verify your email');

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->actingAs($user)->get($url)->assertRedirect();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->actingAs($user->fresh())->get(route('residences.index'))->assertOk();
});

it('refuses an expired or tampered verification link', function () {
    $user = User::factory()->unverified()->create();

    $expired = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->actingAs($user)->get($expired)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('sends a password reset link and lets the user choose a new password', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'reset@example.com']);

    $this->post('/forgot-password', ['email' => 'reset@example.com'])->assertSessionHas('status');
    Notification::assertSentTo($user, ResetPassword::class);

    $token = Password::createToken($user);
    $this->post('/reset-password', [
        'token' => $token, 'email' => 'reset@example.com', 'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1',
    ])->assertRedirect(route('login'));

    expect(Hash::check('brand-new-pass-1', $user->fresh()->password))->toBeTrue();
    $this->post('/login', ['email' => 'reset@example.com', 'password' => 'brand-new-pass-1'])->assertRedirect();
});

it('expires password reset tokens', function () {
    $user = User::factory()->create(['email' => 'late@example.com']);
    $token = Password::createToken($user);

    $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

    $this->post('/reset-password', [
        'token' => $token, 'email' => 'late@example.com', 'password' => 'another-pass-123', 'password_confirmation' => 'another-pass-123',
    ])->assertSessionHasErrors('email');
});

it('shows a clear error for wrong credentials and protects routes', function () {
    User::factory()->create(['email' => 'a@example.com']);

    $this->post('/login', ['email' => 'a@example.com', 'password' => 'wrong'])
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

    $this->get('/residences')->assertRedirect('/login');
    $this->get('/profile')->assertRedirect('/login');
});

it('lets a user enable, confirm and use two-factor authentication', function () {
    $user = User::factory()->create(['email' => 'two@example.com']);

    $this->actingAs($user)->withSession(confirmedPassword())->post(route('two-factor.enable'))->assertRedirect();
    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()->and($user->two_factor_confirmed_at)->toBeNull();

    $this->actingAs($user)->withSession(confirmedPassword())->getJson(route('two-factor.qr-code'))
        ->assertOk()->assertJsonStructure(['svg', 'url']);

    $this->actingAs($user)->withSession(confirmedPassword())->post(route('two-factor.confirm'), ['code' => '000000'])
        ->assertSessionHasErrors('code', errorBag: 'confirmTwoFactorAuthentication');

    $this->actingAs($user)->withSession(confirmedPassword())->post(route('two-factor.confirm'), ['code' => currentOtp($user)])->assertRedirect();
    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();

    $this->actingAs($user)->withSession(confirmedPassword())->getJson(route('two-factor.recovery-codes'))
        ->assertOk()->assertJsonCount(8);
});

it('asks for the 2FA code at login and accepts a code or a recovery code', function () {
    $user = User::factory()->create(['email' => 'two@example.com', 'password' => 'password123']);
    $this->actingAs($user)->withSession(confirmedPassword())->post(route('two-factor.enable'));
    $this->actingAs($user)->withSession(confirmedPassword())->post(route('two-factor.confirm'), ['code' => currentOtp($user->fresh())]);
    auth()->logout();

    $this->post('/login', ['email' => 'two@example.com', 'password' => 'password123'])->assertRedirect(route('two-factor.login'));
    $this->assertGuest();

    $this->post(route('two-factor.login'), ['code' => '123456'])->assertSessionHasErrors('code');
    $this->post(route('two-factor.login'), ['code' => currentOtp($user->fresh(), 1)])->assertRedirect();
    $this->assertAuthenticatedAs($user);

    auth()->logout();
    $recovery = $user->fresh()->recoveryCodes()[0];
    $this->post('/login', ['email' => 'two@example.com', 'password' => 'password123']);
    $this->post(route('two-factor.login'), ['recovery_code' => $recovery])->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

it('lets a user disable two-factor authentication', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(confirmedPassword())->post(route('two-factor.enable'));
    $this->actingAs($user)->withSession(confirmedPassword())->post(route('two-factor.confirm'), ['code' => currentOtp($user->fresh())]);

    $this->actingAs($user)->withSession(confirmedPassword())->delete(route('two-factor.disable'))->assertRedirect();

    expect($user->fresh()->two_factor_secret)->toBeNull();
});

it('requires a recent password confirmation to open the profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile.edit'))->assertRedirect(route('password.confirm'));

    $this->actingAs($user)->post(route('password.confirm.store'), ['password' => 'wrong'])->assertSessionHasErrors('password');
    $this->actingAs($user)->post(route('password.confirm.store'), ['password' => 'password'])->assertRedirect();
    $this->actingAs($user)->withSession(confirmedPassword())->get(route('profile.edit'))->assertOk()->assertSee('Two-factor authentication');
});

it('updates profile information and re-verifies a changed email', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'old@example.com']);

    $this->actingAs($user)->put(route('user-profile-information.update'), ['name' => 'Renamed', 'email' => 'old@example.com'])->assertRedirect();
    expect($user->fresh()->name)->toBe('Renamed')->and($user->fresh()->hasVerifiedEmail())->toBeTrue();

    $this->actingAs($user)->put(route('user-profile-information.update'), ['name' => 'Renamed', 'email' => 'new@example.com'])->assertRedirect();
    expect($user->fresh()->email)->toBe('new@example.com')->and($user->fresh()->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user->fresh(), VerifyEmail::class);

    $this->actingAs(User::factory()->create(['email' => 'taken@example.com']))
        ->put(route('user-profile-information.update'), ['name' => 'X', 'email' => 'new@example.com'])
        ->assertSessionHasErrors('email', errorBag: 'updateProfileInformation');
});

it('changes the password only with the correct current password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'wrong', 'password' => 'new-password-12', 'password_confirmation' => 'new-password-12',
    ])->assertSessionHasErrors('current_password', errorBag: 'updatePassword');

    $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'password', 'password' => 'new-password-12', 'password_confirmation' => 'new-password-12',
    ])->assertRedirect();

    expect(Hash::check('new-password-12', $user->fresh()->password))->toBeTrue();
});

it('uploads and removes a profile photo', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('profile.photo.update'), ['photo' => UploadedFileFake()])->assertSessionHas('success');
    $path = $user->fresh()->profile_photo_path;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);

    $this->actingAs($user)->put(route('profile.photo.update'), ['photo' => UploadedFile::fake()->create('doc.pdf', 10)])
        ->assertSessionHasErrors('photo');

    $this->actingAs($user)->delete(route('profile.photo.destroy'))->assertSessionHas('success');
    expect($user->fresh()->profile_photo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

function UploadedFileFake(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'png');
    file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

    return new UploadedFile($path, 'me.png', 'image/png', null, true);
}

it('deletes the account after password confirmation, except for the last admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'wrong'])->assertSessionHasErrors('password', errorBag: 'deleteAccount');
    expect(User::whereKey($user->id)->exists())->toBeTrue();

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
    expect(User::whereKey($user->id)->exists())->toBeFalse();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrors('password', errorBag: 'deleteAccount');
    expect(User::whereKey($admin->id)->exists())->toBeTrue();
});

it('enforces roles on the server, not only in the interface', function () {
    $resident = User::factory()->create();
    $manager = User::factory()->manager()->create();
    $admin = User::factory()->admin()->create();

    foreach ([route('admin.dashboard'), route('admin.users.index'), route('admin.approvals.index'), route('admin.neighborhoods.index'), route('admin.alerts.index')] as $url) {
        $this->actingAs($resident)->get($url)->assertForbidden();
        $this->actingAs($manager)->get($url)->assertForbidden();
        $this->actingAs($admin)->get($url)->assertOk();
    }
});
