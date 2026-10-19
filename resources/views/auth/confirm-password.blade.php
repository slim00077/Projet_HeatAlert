@extends('layouts.front')

@section('title', 'HeatAlert - Confirm password')
@section('overlay', '1')

@section('content')

<section class="auth-page">
    <div class="auth-card">
        <div class="logo"><span class="logo-icon"><x-icon name="sun" /></span> Heat<span class="logo-accent">Alert</span></div>
        <h1>Confirm your password</h1>
        <p class="auth-sub">This is a secure area. Please confirm your password to continue.</p>

        <x-flash-messages />

        <form action="{{ route('password.confirm.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Confirm</button>
        </form>
    </div>
</section>

@endsection
