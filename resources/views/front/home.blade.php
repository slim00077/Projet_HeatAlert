@extends('layouts.front')

@section('title', 'HeatAlert - Stay Safe, Stay Informed')

@section('content')

<!-- HERO -->

<section class="hero">

    <div class="container hero-content">

        <div class="hero-text">

            <span class="hero-badge">
                ☀ Heat & Power Safety
            </span>

            <h1>
                Stay informed.<br>
                <span>Stay cool. Stay safe.</span>
            </h1>

            <p>
                HeatAlert helps residents anticipate heat waves,
                power outages and find nearby cooling points
                when they need them most.
            </p>

            <div class="hero-buttons">

                <a href="#" class="btn btn-primary btn-large">
                    View Heat Alerts
                </a>

                <a href="#" class="btn btn-outline btn-large">
                    Find Cooling Points
                </a>

            </div>

        </div>

        <div class="hero-card">

            <div class="temperature">

                <span class="temperature-icon">☀</span>

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
                Check the latest alerts and prepare
                before conditions become dangerous.
            </p>

        </div>

    </div>

</section>


<!-- QUICK ACCESS -->

<section class="quick-access">

    <div class="container">

        <div class="section-heading">

            <span>Quick Access</span>

            <h2>
                What do you need?
            </h2>

            <p>
                Access the information that matters most during
                periods of extreme heat.
            </p>

        </div>


        <div class="feature-grid">

            <a href="#" class="feature-card">

                <div class="feature-icon heat-icon">
                    ☀
                </div>

                <h3>Heat Alerts</h3>

                <p>
                    Check current and upcoming heat-wave alerts.
                </p>

                <span class="feature-link">
                    View alerts →
                </span>

            </a>


            <a href="#" class="feature-card">

                <div class="feature-icon outage-icon">
                    ⚡
                </div>

                <h3>Power Outages</h3>

                <p>
                    See current or planned power interruptions.
                </p>

                <span class="feature-link">
                    View outages →
                </span>

            </a>


            <a href="#" class="feature-card">

                <div class="feature-icon cooling-icon">
                    ❄
                </div>

                <h3>Cooling Points</h3>

                <p>
                    Find parks, fountains and air-conditioned
                    places nearby.
                </p>

                <span class="feature-link">
                    Find a place →
                </span>

            </a>


            <a href="#" class="feature-card">

                <div class="feature-icon advice-icon">
                    💧
                </div>

                <h3>Safety Advice</h3>

                <p>
                    Get practical advice for heat and
                    power-cut situations.
                </p>

                <span class="feature-link">
                    Read advice →
                </span>

            </a>

        </div>

    </div>

</section>


<!-- HOW IT WORKS -->

<section class="how-section">

    <div class="container">

        <div class="section-heading">

            <span>How HeatAlert helps</span>

            <h2>
                Be prepared before the heat arrives
            </h2>

        </div>


        <div class="steps-grid">

            <div class="step">

                <div class="step-number">
                    01
                </div>

                <h3>
                    Check alerts
                </h3>

                <p>
                    Follow heat-wave and power-outage
                    information for your area.
                </p>

            </div>


            <div class="step">

                <div class="step-number">
                    02
                </div>

                <h3>
                    Find a cooling point
                </h3>

                <p>
                    Discover accessible places where you
                    can stay cool and safe.
                </p>

            </div>


            <div class="step">

                <div class="step-number">
                    03
                </div>

                <h3>
                    Follow practical advice
                </h3>

                <p>
                    Learn how to stay hydrated, save energy
                    and protect sensitive equipment.
                </p>

            </div>

        </div>

    </div>

</section>


<!-- AI SECTION -->

<section class="ai-section">

    <div class="container ai-content">

        <div>

            <span class="ai-label">
                AI ASSISTANT
            </span>

            <h2>
                Need personalized advice?
            </h2>

            <p>
                HeatAlert will provide intelligent recommendations
                based on heat conditions, outages and your situation.
            </p>

            <a href="#" class="btn btn-white">
                Ask HeatAlert AI
            </a>

        </div>

        <div class="ai-icon">
            ✦
        </div>

    </div>

</section>

@endsection