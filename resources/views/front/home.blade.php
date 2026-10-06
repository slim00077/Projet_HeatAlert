@extends('layouts.front')

@section('title', 'HeatAlert - Stay Safe During Heatwaves')
@section('overlay', '1')

@section('content')

<section class="hero">

    <div class="container hero-content">

        <div class="hero-text">

            <span class="hero-badge">
                <x-icon name="sun" class="icon" style="width:1rem;height:1rem" /> Heat &amp; Power Safety
            </span>

            <h1>
                Stay Safe<br>
                During <span>Heatwaves</span>
            </h1>

            <p>
                Real-time information, alerts and community support
                to help you stay informed and protected.
            </p>

            <div class="hero-buttons">
                <a href="#" class="btn btn-primary btn-large">View Heat Alerts</a>
                <a href="#" class="btn btn-ghost btn-large">Learn More <x-icon name="arrow" /></a>
            </div>

        </div>

        <div class="hero-card">

            <div class="temperature">
                <span class="temperature-icon"><x-icon name="warning" /></span>
                <div>
                    <small>Current Alert Status</small>
                    <strong>Heat Alert</strong>
                </div>
            </div>

            <div class="alert-level">
                <span></span>
                High temperature risk
            </div>

            <p>
                Check the latest alerts and prepare before
                conditions become dangerous.
            </p>

        </div>

    </div>

    <div class="container hero-features">

        <x-feature-card title="Heat Alerts" icon="warning" tone="coral">Latest warnings and updates</x-feature-card>
        <x-feature-card title="Cooling Points" icon="snow" tone="blue">Find nearby cool spots</x-feature-card>
        <x-feature-card title="Power Outages" icon="bolt" tone="purple">Report and view outages</x-feature-card>
        <x-feature-card title="Practical Advice" icon="bulb" tone="green" href="{{ route('advice.index') }}">Tips to stay safe</x-feature-card>

    </div>

</section>

@endsection
