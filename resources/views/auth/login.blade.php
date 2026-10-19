@extends('layouts.front')

@section('title', 'HeatAlert - Login')
@section('overlay', '1')

@section('content')

<section class="auth-page">
    <div class="auth-card">
        <div class="logo"><span class="logo-icon"><x-icon name="sun" /></span> Heat<span class="logo-accent">Alert</span></div>
        <h1>Welcome Back</h1>
        <p class="auth-sub">Sign in to continue to your account</p>

        <x-flash-messages />

        <form action="{{ route('login') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••">
            </div>
            <div class="form-group">
                <label class="check-row"><input type="checkbox" name="remember" value="1"> Remember me</label>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
            <p class="auth-foot" style="margin-top:.8rem"><a href="{{ route('password.request') }}">Forgot your password?</a></p>
        </form>

        <p class="auth-foot">Don't have an account? <a href="{{ route('register') }}">Register</a></p>
    </div>
</section>

@endsection
