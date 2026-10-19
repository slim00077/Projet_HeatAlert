@extends('layouts.front')

@section('title', 'HeatAlert - Verify your email')
@section('overlay', '1')

@section('content')

<section class="auth-page">
    <div class="auth-card">
        <div class="logo"><span class="logo-icon"><x-icon name="sun" /></span> Heat<span class="logo-accent">Alert</span></div>
        <h1>Verify your email</h1>
        <p class="auth-sub">We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Click it to activate your account.</p>

        @if (session('status') == 'verification-link-sent')
            <div class="flash flash-success">A new verification link has been sent to your email address.</div>
        @endif

        <form action="{{ route('verification.send') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-primary btn-block">Resend verification email</button>
        </form>

        <form action="{{ route('logout') }}" method="POST" style="margin-top:.8rem">
            @csrf
            <button type="submit" class="btn btn-outline btn-block">Log out</button>
        </form>
    </div>
</section>

@endsection
