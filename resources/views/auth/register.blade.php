@extends('layouts.front')

@section('title', 'HeatAlert - Register')
@section('overlay', '1')

@section('content')

<section class="auth-page">
    <div class="auth-card">
        <div class="logo"><span class="logo-icon"><x-icon name="sun" /></span> Heat<span class="logo-accent">Alert</span></div>
        <h1>Create Your Account</h1>
        <p class="auth-sub">Join the HeatAlert community</p>

        <x-flash-messages />

        <form action="{{ route('register') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Your name" autofocus>
            </div>
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="At least 8 characters">
            </div>
            <div class="form-group">
                <label for="password_confirmation">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Register</button>
        </form>

        <p class="auth-foot">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
    </div>
</section>

@endsection
