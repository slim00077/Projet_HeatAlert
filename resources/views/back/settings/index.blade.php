@extends('layouts.back')

@section('title', 'HeatAlert - Settings')

@section('content')

<div class="page-header">
    <div>
        <h1>Settings</h1>
        <p class="subtitle">Personalize how HeatAlert looks on this device</p>
    </div>
</div>

<div class="form-card" style="margin-inline: 0">
    <h3>Appearance</h3>
    <p class="field-hint">Choose a theme. It applies to the whole site and is remembered in this browser.</p>

    <div class="theme-options">
        <button type="button" class="btn btn-outline" data-theme-option="light">Light</button>
        <button type="button" class="btn btn-outline" data-theme-option="dark">Dark</button>
        <button type="button" class="btn btn-outline" data-theme-option="system">Same as system</button>
    </div>
</div>

<div class="form-card" style="margin-inline: 0">
    <h3>Account</h3>
    <p><strong>{{ auth()->user()->name }}</strong><br>{{ auth()->user()->email }}</p>
    <a href="{{ route('admin.users.edit', auth()->user()) }}" class="btn btn-outline">Edit my account</a>
</div>

@endsection
