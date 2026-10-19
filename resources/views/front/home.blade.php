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
                HeatAlert is a community platform that brings together heat warnings,
                cooling points, power outage reports and practical advice, so every
                resident can stay informed and protected.
            </p>

            <div class="hero-buttons">
                <a href="{{ route('front.alerts.index') }}" class="btn btn-primary btn-large">View Heat Alerts</a>
                <a href="{{ route('pages.about') }}" class="btn btn-ghost btn-large">Learn More <x-icon name="arrow" /></a>
            </div>

        </div>

        <div class="hero-card">

            <div class="temperature">
                <span class="temperature-icon"><x-icon name="warning" /></span>
                <div>
                    <small>Current Alert Status</small>
                    <strong>{{ $activeAlertsCount > 0 ? $activeAlertsCount.' active '.\Illuminate\Support\Str::plural('alert', $activeAlertsCount) : 'All clear' }}</strong>
                </div>
            </div>

            <div class="alert-level">
                <span></span>
                {{ $highRiskCount > 0 ? 'High temperature risk in '.$highRiskCount.' '.\Illuminate\Support\Str::plural('area', $highRiskCount) : ($activeAlertsCount > 0 ? 'Stay alert and hydrated' : 'No heat risk reported') }}
            </div>

            <p>
                Check the latest alerts and prepare before
                conditions become dangerous.
            </p>

        </div>

    </div>

    <div class="container hero-features">

        <x-feature-card title="Heat Alerts" icon="warning" tone="coral" href="{{ route('front.alerts.index') }}">Latest warnings and updates</x-feature-card>
        <x-feature-card title="Cooling Points" icon="snow" tone="blue" href="{{ route('cool-spots.index') }}">Find nearby cool spots</x-feature-card>
        <x-feature-card title="Power Outages" icon="bolt" tone="purple" href="{{ route('front.power-outages.index') }}">Report and view outages</x-feature-card>
        <x-feature-card title="Practical Advice" icon="bulb" tone="green" href="{{ route('advice.index') }}">Tips to stay safe</x-feature-card>

    </div>

</section>

<section class="home-stats">
    <div class="container home-stats-grid">
        <x-stat-card label="Active heat alerts" :value="$activeAlertsCount" icon="warning" tone="coral" />
        <x-stat-card label="Cooling points" :value="$coolSpotsCount" icon="snow" tone="blue" />
        <x-stat-card label="Ongoing outages" :value="$outagesCount" icon="bolt" tone="purple" />
        <x-stat-card label="Safety tips" :value="$adviceCount" icon="bulb" tone="green" />
    </div>
</section>

<section class="home-section" id="about">
    <div class="container home-about">
        <div class="home-about-text">
            <span class="home-eyebrow">About HeatAlert</span>
            <h2>One place to prepare for, and get through, extreme heat</h2>
            <p>
                Heatwaves and the power cuts that often follow them put families, elderly
                people and vulnerable residents at risk. HeatAlert connects residents and
                local administrators so that warnings travel fast, safe places are easy
                to find and problems in your building are reported and followed up.
            </p>
            <ul class="home-checklist">
                <li><x-icon name="check" /> Official heat alerts by neighborhood, with severity levels</li>
                <li><x-icon name="check" /> A directory of parks, libraries and other places to cool down</li>
                <li><x-icon name="check" /> Power outage tracking and resident reports</li>
                <li><x-icon name="check" /> Manage your residences and follow every issue you report</li>
            </ul>
        </div>

        <div class="home-steps">
            <h3>How it works</h3>
            <ol>
                <li><span>1</span><div><strong>Stay informed</strong><p>Check the alerts for your neighborhood and the current risk level.</p></div></li>
                <li><span>2</span><div><strong>Find relief</strong><p>Locate the nearest cooling point and read practical safety advice.</p></div></li>
                <li><span>3</span><div><strong>Report and follow up</strong><p>Register your residence, report issues and track their status until resolved.</p></div></li>
            </ol>
        </div>
    </div>
</section>

<section class="home-section home-section-alt">
    <div class="container">
        <div class="home-heading">
            <div>
                <span class="home-eyebrow">Live updates</span>
                <h2>Current heat alerts</h2>
            </div>
            <a href="{{ route('front.alerts.index') }}" class="card-link">All alerts <x-icon name="arrow" /></a>
        </div>

        <div class="advice-grid">
            @forelse ($latestAlerts as $alert)
                <article class="advice-card">
                    <div>
                        <x-severity-badge :severity="$alert->severity_level" />
                    </div>
                    <h3>{{ $alert->title }}</h3>
                    <p class="advice-summary">{{ \Illuminate\Support\Str::limit($alert->description, 130) }}</p>
                    <span class="chip">{{ $alert->neighborhood->name ?? 'N/A' }}</span>
                </article>
            @empty
                <div class="empty-state" style="grid-column: 1 / -1">No active heat alert right now. Everything is calm.</div>
            @endforelse
        </div>
    </div>
</section>

<section class="home-section">
    <div class="container">
        <div class="home-heading">
            <div>
                <span class="home-eyebrow">Cool down</span>
                <h2>Cooling points near you</h2>
            </div>
            <a href="{{ route('cool-spots.index') }}" class="card-link">All cooling points <x-icon name="arrow" /></a>
        </div>

        <div class="advice-grid">
            @forelse ($coolSpots as $spot)
                <article class="advice-card">
                    <span class="chip">{{ $spot->type->name }}</span>
                    <h3>{{ $spot->name }}</h3>
                    <p class="advice-summary"><x-icon name="pin" style="width:1rem;height:1rem;vertical-align:-0.15rem" /> {{ $spot->address }}</p>
                    @if ($spot->opening_hours)
                        <p class="advice-summary"><x-icon name="clock" style="width:1rem;height:1rem;vertical-align:-0.15rem" /> {{ $spot->opening_hours }}</p>
                    @endif
                    <a href="{{ route('cool-spots.show', $spot) }}" class="card-link">View details <x-icon name="arrow" /></a>
                </article>
            @empty
                <div class="empty-state" style="grid-column: 1 / -1">No cooling point has been added yet.</div>
            @endforelse
        </div>
    </div>
</section>

<section class="home-section home-section-alt">
    <div class="container">
        <div class="home-heading">
            <div>
                <span class="home-eyebrow">Be prepared</span>
                <h2>Practical advice</h2>
            </div>
            <a href="{{ route('advice.index') }}" class="card-link">All advice <x-icon name="arrow" /></a>
        </div>

        <div class="advice-grid">
            @forelse ($advices as $advice)
                <article class="advice-card">
                    <span class="chip">{{ $advice->category->name }}</span>
                    <h3>{{ $advice->title }}</h3>
                    <p class="advice-summary">{{ \Illuminate\Support\Str::limit($advice->summary ?? $advice->content, 130) }}</p>
                    <a href="{{ route('advice.show', $advice) }}" class="card-link">Read advice <x-icon name="arrow" /></a>
                </article>
            @empty
                <div class="empty-state" style="grid-column: 1 / -1">No advice has been published yet.</div>
            @endforelse
        </div>
    </div>
</section>

<section class="home-section">
    <div class="container">
        <div class="home-cta">
            <div>
                <h2>Be part of a safer community</h2>
                <p>Register your residence, report problems and help your neighbors stay safe during the next heatwave.</p>
            </div>
            <div class="hero-buttons">
                @auth
                    <a href="{{ route('residences.index') }}" class="btn btn-primary btn-large">My residences</a>
                    <a href="{{ route('resident-reports.create') }}" class="btn btn-ghost btn-large">Report an issue</a>
                @else
                    <a href="{{ route('register') }}" class="btn btn-primary btn-large">Create an account</a>
                    <a href="{{ route('login') }}" class="btn btn-ghost btn-large">Log in</a>
                @endauth
            </div>
        </div>
    </div>
</section>

@endsection
