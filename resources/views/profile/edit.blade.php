@extends('layouts.front')

@section('title', 'HeatAlert - My profile')

@section('content')

@php
    $statusMessages = [
        'profile-information-updated' => 'Profile information updated.',
        'password-updated' => 'Password updated.',
        'two-factor-authentication-enabled' => 'Two-factor authentication started: scan the QR code and enter a code to finish.',
        'two-factor-authentication-confirmed' => 'Two-factor authentication is now active. Save your recovery codes.',
        'two-factor-authentication-disabled' => 'Two-factor authentication disabled.',
        'recovery-codes-generated' => 'New recovery codes generated.',
    ];
    $pendingTwoFactor = $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;
    $twoFactorActive = $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null;
@endphp

<section class="fx-page page-section">
    <div class="container profile-page">

        <div class="section-heading">
            <span>{{ ucfirst($user->role) }}</span>
            <h2>My profile</h2>
            <p>Manage your information, security and account.</p>
        </div>

        <x-flash-messages />
        @if (session('status') && isset($statusMessages[session('status')]))
            <div class="flash flash-success">{{ $statusMessages[session('status')] }}</div>
        @endif
        @if (session('status') == 'verification-link-sent')
            <div class="flash flash-success">A new verification link has been sent to your email address.</div>
        @endif

        {{-- Profile information and photo --}}
        <div class="form-card profile-card">
            <h3>Profile information</h3>

            <div class="profile-photo">
                @if ($user->profilePhotoUrl())
                    <img src="{{ $user->profilePhotoUrl() }}" alt="{{ $user->name }}" class="avatar-lg">
                @else
                    <div class="avatar-lg avatar-initials">{{ $user->initials() }}</div>
                @endif
                <div>
                    <form action="{{ route('profile.photo.update') }}" method="POST" enctype="multipart/form-data" class="toolbar">
                        @csrf
                        @method('PUT')
                        <input type="file" name="photo" accept="image/png,image/jpeg,image/webp" required>
                        <button type="submit" class="btn btn-outline btn-sm">Upload photo</button>
                    </form>
                    @error('photo')<p class="field-error">{{ $message }}</p>@enderror
                    @if ($user->profilePhotoUrl())
                        <form action="{{ route('profile.photo.destroy') }}" method="POST" style="margin-top:.4rem">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="link-danger">Remove photo</button>
                        </form>
                    @endif
                </div>
            </div>

            <form action="{{ route('user-profile-information.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="form-group @error('name', 'updateProfileInformation') has-error @enderror">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                    @error('name', 'updateProfileInformation')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="form-group @error('email', 'updateProfileInformation') has-error @enderror">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                    @error('email', 'updateProfileInformation')<p class="field-error">{{ $message }}</p>@enderror
                    @unless ($user->hasVerifiedEmail())
                        <p class="field-hint">Your email is not verified.
                            <button type="submit" form="resend-verification" class="link-danger">Resend verification email</button>
                        </p>
                    @endunless
                </div>
                <button type="submit" class="btn btn-primary">Save</button>
            </form>
            <form action="{{ route('verification.send') }}" method="POST" id="resend-verification">@csrf</form>
        </div>

        {{-- Password --}}
        <div class="form-card profile-card">
            <h3>Change password</h3>
            <form action="{{ route('user-password.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="form-group @error('current_password', 'updatePassword') has-error @enderror">
                    <label for="current_password">Current password</label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    @error('current_password', 'updatePassword')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="form-group @error('password', 'updatePassword') has-error @enderror">
                    <label for="password">New password</label>
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                    @error('password', 'updatePassword')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Confirm new password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary">Update password</button>
            </form>
        </div>

        {{-- Two-factor authentication --}}
        <div class="form-card profile-card" id="two-factor"
             data-qr-url="{{ route('two-factor.qr-code') }}"
             data-secret-url="{{ route('two-factor.secret-key') }}"
             data-codes-url="{{ route('two-factor.recovery-codes') }}">
            <h3>Two-factor authentication
                @if ($twoFactorActive)<span class="status-badge status-approved">Enabled</span>@endif
            </h3>

            @if ($twoFactorActive)
                <p>Your account is protected with an authenticator app. Keep your recovery codes somewhere safe.</p>
                <div id="recovery-codes" class="recovery-codes"></div>
                <div class="toolbar">
                    <form action="{{ route('two-factor.regenerate-recovery-codes') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-sm">Regenerate recovery codes</button>
                    </form>
                    <form action="{{ route('two-factor.disable') }}" method="POST" onsubmit="return confirm('Disable two-factor authentication?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline btn-sm">Disable</button>
                    </form>
                </div>
            @elseif ($pendingTwoFactor)
                <p>Scan this QR code with Google Authenticator, Authy or a similar app, then enter the 6-digit code to confirm.</p>
                <div id="qr-code" class="qr-code"></div>
                <p class="field-hint">Or enter this key manually: <strong id="secret-key"></strong></p>
                <form action="{{ route('two-factor.confirm') }}" method="POST">
                    @csrf
                    <div class="form-group @error('code', 'confirmTwoFactorAuthentication') has-error @enderror">
                        <label for="two_factor_code">Authentication code</label>
                        <input type="text" id="two_factor_code" name="code" inputmode="numeric" autocomplete="one-time-code" required>
                        @error('code', 'confirmTwoFactorAuthentication')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="toolbar">
                        <button type="submit" class="btn btn-primary btn-sm">Confirm and enable</button>
                    </div>
                </form>
                <form action="{{ route('two-factor.disable') }}" method="POST" style="margin-top:.6rem">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="link-danger">Cancel setup</button>
                </form>
            @else
                <p>Add a second step to your login: a 6-digit code from an authenticator app.</p>
                <form action="{{ route('two-factor.enable') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">Enable two-factor authentication</button>
                </form>
            @endif
        </div>

        {{-- Delete account --}}
        <div class="form-card profile-card">
            <h3>Delete account</h3>
            <p>This permanently removes your account{{ $user->isManager() ? ' and the residences you manage' : '' }}. This cannot be undone.</p>
            <form action="{{ route('profile.destroy') }}" method="POST" onsubmit="return confirm('Delete your account permanently?')">
                @csrf
                @method('DELETE')
                <div class="form-group @error('password', 'deleteAccount') has-error @enderror">
                    <label for="delete_password">Confirm with your password</label>
                    <input type="password" id="delete_password" name="password" required>
                    @error('password', 'deleteAccount')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn btn-outline">Delete my account</button>
            </form>
        </div>

    </div>
</section>

<script>
    (function () {
        const box = document.getElementById('two-factor');
        const get = (url) => fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then((r) => r.json());

        const qr = document.getElementById('qr-code');
        if (qr) {
            get(box.dataset.qrUrl).then((data) => { qr.innerHTML = data.svg; });
            get(box.dataset.secretUrl).then((data) => { document.getElementById('secret-key').textContent = data.secretKey; });
        }

        const codes = document.getElementById('recovery-codes');
        if (codes) {
            get(box.dataset.codesUrl).then((list) => {
                codes.innerHTML = '<p><strong>Recovery codes</strong> (each works once):</p><ul>' + list.map((c) => '<li><code>' + c + '</code></li>').join('') + '</ul>';
            });
        }
    })();
</script>

@endsection
