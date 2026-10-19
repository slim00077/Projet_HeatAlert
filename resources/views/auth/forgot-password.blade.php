@extends('layouts.front')

@section('title', 'HeatAlert - Forgot password')
@section('overlay', '1')

@section('content')

<section class="auth-page">
    <div class="auth-card">
        <div class="logo"><span class="logo-icon"><x-icon name="sun" /></span> Heat<span class="logo-accent">Alert</span></div>
        <h1>Forgot your password?</h1>
        <p class="auth-sub">Enter your email and we will send you a link to choose a new one.</p>

        @if (session('status'))
            <div class="flash flash-success">{{ session('status') }}</div>
        @endif
        <x-flash-messages />

        <form action="{{ route('password.email') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autofocus required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Email me a reset link</button>
        </form>

        <p class="auth-foot"><a href="{{ route('login') }}">Back to login</a></p>
    </div>
</section>

@endsection
