@extends('layouts.front')

@section('title', 'HeatAlert - Reset password')
@section('overlay', '1')

@section('content')

<section class="auth-page">
    <div class="auth-card">
        <div class="logo"><span class="logo-icon"><x-icon name="sun" /></span> Heat<span class="logo-accent">Alert</span></div>
        <h1>Choose a new password</h1>
        <p class="auth-sub">This link expires after {{ config('auth.passwords.users.expire') }} minutes.</p>

        <x-flash-messages />

        <form action="{{ route('password.update') }}" method="POST">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="{{ old('email', $request->email) }}" required>
            </div>
            <div class="form-group">
                <label for="password">New password</label>
                <input type="password" id="password" name="password" required minlength="8" autofocus>
            </div>
            <div class="form-group">
                <label for="password_confirmation">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Reset password</button>
        </form>
    </div>
</section>

@endsection
