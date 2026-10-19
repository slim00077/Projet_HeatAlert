@extends('layouts.front')

@section('title', 'HeatAlert - About')

@section('content')

<section class="page-section">
    <div class="container profile-page">

        <div class="section-heading">
            <span>About</span>
            <h2>Stay safe during heat waves</h2>
            <p>HeatAlert warns neighborhoods about dangerous heat and helps communities look after each other.</p>
        </div>

        <div class="form-card profile-card">
            <h3>What it does</h3>
            <ul class="tips-list">
                <li>Follows the weather forecast of every monitored neighborhood and raises alerts automatically.</li>
                <li>Shows alerts, temperatures and safety advice on an interactive map.</li>
                <li>Notifies residents of the affected neighborhood by email and in the app.</li>
                <li>Lets residents report dangerous hot spots, and answers safety questions with an AI assistant.</li>
            </ul>
        </div>

        <div class="form-card profile-card">
            <h3>Who uses it</h3>
            <ul class="tips-list">
                <li><strong>Residents</strong> follow the alerts of their neighborhood and report issues in their residence.</li>
                <li><strong>Residence managers</strong> register the residences of their neighborhood (validated by an admin) and add the residents who live there.</li>
                <li><strong>Administrators</strong> manage neighborhoods, alerts, users and residence approvals.</li>
            </ul>
        </div>

        <p class="back-link">
            <a href="{{ route('front.alerts.index') }}" class="btn btn-primary">See current heat alerts</a>
            <a href="{{ route('pages.emergency') }}" class="btn btn-outline">Emergency information</a>
        </p>

    </div>
</section>

@endsection
